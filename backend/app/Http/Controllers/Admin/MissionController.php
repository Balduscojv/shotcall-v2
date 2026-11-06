<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\MissionResource;
use App\Models\Cosmetic;
use App\Models\League;
use App\Models\Mission;
use App\Services\Missions\ConditionCatalog;
use App\Services\Missions\Conditions\Condition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Missões na moderação (só admin): condição do catálogo com parâmetros, recompensas, segredo
 * e dica, ativa e período. A missão vale dali para frente, nos próximos eventos.
 */
class MissionController extends Controller
{
    public function __construct(private ConditionCatalog $catalog) {}

    /** Tipos de condição disponíveis, com os parâmetros que cada um aceita. */
    public function conditions(): JsonResponse
    {
        return response()->json(['data' => array_values(array_map(fn (Condition $c) => [
            'key' => $c->key(),
            'label' => $c->label(),
            'description' => $c->description(),
            'params' => $c->params(),
        ], $this->catalog->all()))]);
    }

    /**
     * Ligas para vincular a uma missão (parâmetro de liga): busca pelo nome, sem a global.
     * Inclui as encerradas, para quem quiser premiar uma temporada que já acabou.
     */
    public function leagues(Request $request): JsonResponse
    {
        $term = mb_strtolower(trim((string) $request->query('q')));
        $ids = array_filter(array_map('intval', explode(',', (string) $request->query('ids'))));

        $leagues = League::where('is_global', false)
            ->when($ids, fn ($q) => $q->whereKey($ids), fn ($q) => $q->when($term !== '', fn ($q) => $q->whereRaw('lower(name) LIKE ?', ["%{$term}%"])))
            ->withCount('members')->orderByDesc('members_count')->orderBy('name')->limit(30)->get();

        return response()->json(['data' => $leagues->map(fn (League $l) => [
            'id' => $l->id, 'name' => $l->name, 'slug' => $l->slug, 'color' => $l->color,
            'members_count' => $l->members_count, 'finished' => $l->finished_at !== null,
        ])]);
    }

    public function index(): AnonymousResourceCollection
    {
        return MissionResource::collection(Mission::with('rewards')->orderByDesc('active')->orderBy('name')->get());
    }

    public function show(Mission $mission): MissionResource
    {
        return new MissionResource($mission->load('rewards'));
    }

    public function store(Request $request): JsonResponse
    {
        $mission = new Mission(['key' => $this->uniqueKey((string) $request->input('name'))]);
        $this->save($request, $mission);

        return (new MissionResource($mission->load('rewards')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Mission $mission): MissionResource
    {
        $this->save($request, $mission);

        return new MissionResource($mission->load('rewards'));
    }

    /** Liga/desliga a missão direto da lista. */
    public function toggle(Request $request, Mission $mission): MissionResource
    {
        $mission->update($request->validate(['active' => ['required', 'boolean']]));

        return new MissionResource($mission->load('rewards'));
    }

    /** Apagar não tira o prêmio de quem já cumpriu. */
    public function destroy(Mission $mission): JsonResponse
    {
        $mission->delete();

        return response()->json(['message' => "Missão {$mission->name} apagada."]);
    }

    private function save(Request $request, Mission $mission): void
    {
        $condition = $this->catalog->find((string) $request->input('condition', $mission->condition));

        $rules = [
            'name' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:200'],
            'condition' => ['required', Rule::in(array_keys($this->catalog->all()))],
            'params' => ['array'],
            'rewards' => ['required', 'array', 'min:1'],
            'rewards.*' => ['string', 'distinct', 'exists:cosmetics,key'],
            'secret' => ['boolean'],
            'hint' => ['nullable', 'required_if_accepted:secret', 'string', 'max:120'],
            'active' => ['boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ];
        foreach ($condition?->params() ?? [] as $p) {
            $rules["params.{$p['name']}"] = match ($p['type'] ?? 'int') {
                'choice' => ['required', 'integer', Rule::in(array_column($p['options'], 'value'))],
                // 0 = qualquer liga; senão, uma liga que não seja a global.
                'league' => ['required', 'integer', function (string $attr, mixed $value, \Closure $fail) {
                    if ((int) $value !== 0 && ! League::whereKey((int) $value)->where('is_global', false)->exists()) {
                        $fail('Liga não encontrada.');
                    }
                }],
                default => ['required', 'integer', "min:{$p['min']}", "max:{$p['max']}"],
            };
        }

        $data = $request->validate($rules, [
            'name.required' => 'Dê um nome à missão.',
            'condition.required' => 'Escolha a condição.',
            'condition.in' => 'Condição desconhecida.',
            'rewards.required' => 'Escolha pelo menos um prêmio de recompensa.',
            'rewards.min' => 'Escolha pelo menos um prêmio de recompensa.',
            'rewards.*.exists' => 'Prêmio não encontrado.',
            'hint.required_if_accepted' => 'Missão secreta precisa de uma dica.',
            'ends_at.after' => 'O fim precisa ser depois do início.',
            'params.*.required' => 'Preencha este valor.',
            'params.*.integer' => 'Use um número inteiro.',
            'params.*.min' => 'O mínimo é :min.',
            'params.*.max' => 'O máximo é :max.',
            'params.*.in' => 'Escolha uma das opções.',
        ]);

        $mission->fill([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'condition' => $condition->key(),
            'params' => $this->catalog->normalize($condition, $data['params'] ?? []),
            'secret' => $data['secret'] ?? true,
            'hint' => $data['hint'] ?? null,
            'active' => $data['active'] ?? true,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
        ])->save();

        $mission->rewards()->sync(Cosmetic::whereIn('key', $data['rewards'])->pluck('id'));
    }

    private function uniqueKey(string $name): string
    {
        $base = Str::slug($name) ?: 'missao';
        $key = $base;
        for ($n = 2; Mission::where('key', $key)->exists(); $n++) {
            $key = "{$base}-{$n}";
        }

        return $key;
    }
}
