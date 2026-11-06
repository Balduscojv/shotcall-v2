<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveLeagueRequest;
use App\Http\Resources\LeagueResource;
use App\Http\Resources\ProfileLeagueResource;
use App\Http\Resources\UserResource;
use App\Models\League;
use App\Models\LeagueStanding;
use App\Models\Round;
use App\Models\User;
use App\Services\Leagues\LeagueRanking;
use App\Services\Leagues\LeagueService;
use App\Services\Leagues\LivePartials;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Ligas: as minhas, busca das públicas, criar, entrar, sair, editar, membros e o ranking. */
class LeagueController extends Controller
{
    /** Linhas do ranking na resposta. A global tem milhares; a minha posição vem à parte. */
    private const RANKING_ROWS = 100;

    public function __construct(
        private LeagueService $leagues,
        private LeagueRanking $ranking,
        private LivePartials $live,
    ) {}

    /** Minhas ligas (a global primeiro), com a posição da última foto do ranking. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $me = $request->user();
        $leagues = $me->leagues()
            ->withCount('members')
            ->addSelect(['rank' => LeagueStanding::select('rank')
                ->whereColumn('league_standings.league_id', 'leagues.id')
                ->where('league_standings.user_id', $me->id)
                ->orderByDesc('round_id')->limit(1)])
            ->orderByDesc('is_global')->orderBy('name')
            ->get();

        return ProfileLeagueResource::collection($leagues)->additional(['meta' => [
            'count' => $this->leagues->leaguesCount($me),
            'max' => LeagueService::MAX_LEAGUES,
        ]]);
    }

    /** Ligas públicas pelo nome. Sem termo, as mais cheias. */
    public function search(Request $request): JsonResponse
    {
        $me = $request->user();
        $term = mb_strtolower(trim((string) $request->query('q')));
        $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        $leagues = League::where('privacy', 'PUBLIC')->where('is_global', false)->whereNull('finished_at')
            ->when($term !== '', fn ($q) => $q->whereRaw('lower(name) LIKE ? ESCAPE ?', [$pattern, '!']))
            ->withCount('members')
            ->withExists(['members as is_member' => fn ($q) => $q->whereKey($me->id)])
            ->orderByDesc('members_count')->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json(['data' => $leagues->map(fn (League $l) => [
            'slug' => $l->slug, 'name' => $l->name, 'color' => $l->color, 'description' => $l->description,
            'competitions' => $l->competitions, 'members_count' => $l->members_count, 'max_members' => $l->max_members,
            'is_member' => (bool) $l->is_member,
        ])]);
    }

    public function store(SaveLeagueRequest $request): LeagueResource
    {
        $league = $this->leagues->create($request->user(), $request->validated());

        return new LeagueResource($league->loadCount('members'));
    }

    public function show(League $league): LeagueResource
    {
        $this->authorizeView($league);

        return new LeagueResource($league->loadCount('members'));
    }

    public function update(SaveLeagueRequest $request, League $league): LeagueResource
    {
        Gate::authorize('update', $league);
        $league->update($request->validated());

        return new LeagueResource($league->loadCount('members'));
    }

    public function destroy(League $league): Response
    {
        Gate::authorize('delete', $league);
        $league->delete();

        return response()->noContent();
    }

    /** Prévia do convite: quem tem o código vê nome, escudo e lotação, mesmo de liga privada. */
    public function invite(Request $request, string $code): JsonResponse
    {
        $league = League::where('invite_code', strtoupper($code))->where('is_global', false)
            ->with('owner')->withCount('members')->firstOrFail();

        return response()->json(['data' => [
            'slug' => $league->slug, 'name' => $league->name, 'color' => $league->color, 'description' => $league->description,
            'privacy' => $league->privacy, 'competitions' => $league->competitions,
            'members_count' => $league->members_count, 'max_members' => $league->max_members,
            'owner' => new UserResource($league->owner),
            'is_member' => $league->hasMember($request->user()),
        ]]);
    }

    /** Entrar com o código de convite. */
    public function joinByCode(Request $request): JsonResponse
    {
        $code = strtoupper(trim((string) $request->input('code')));
        $league = League::where('invite_code', $code)->where('is_global', false)->first();
        if (! $league) {
            throw ValidationException::withMessages(['code' => 'Código inválido. Confira com quem te convidou.']);
        }
        $this->leagues->join($request->user(), $league);

        return response()->json(['data' => ['slug' => $league->slug]]);
    }

    /** Entrar numa liga pública direto, sem código. */
    public function join(Request $request, League $league): JsonResponse
    {
        if ($league->privacy !== 'PUBLIC') {
            throw ValidationException::withMessages(['code' => 'Essa liga é privada: só entra com o código de convite.']);
        }
        $this->leagues->join($request->user(), $league);

        return response()->json(['data' => ['slug' => $league->slug]]);
    }

    public function leave(Request $request, League $league): Response
    {
        $this->leagues->leave($request->user(), $league);

        return response()->noContent();
    }

    public function regenerateCode(League $league): JsonResponse
    {
        Gate::authorize('moderate', $league);

        return response()->json(['data' => ['invite_code' => $this->leagues->regenerateCode($league)]]);
    }

    /** Membros com o papel de cada um: dono, admins e depois os outros, por nome. */
    public function members(Request $request, League $league): JsonResponse
    {
        $this->authorizeView($league);
        $order = ['OWNER' => 0, 'ADMIN' => 1, 'MEMBER' => 2];
        $members = $league->members()->withEquipped()->get()
            ->sortBy(fn (User $u) => [$order[$u->pivot->role] ?? 3, mb_strtolower($u->name)])->values();

        return response()->json(['data' => $members->map(fn (User $u) => [
            ...(new UserResource($u))->resolve($request),
            'league_role' => $u->pivot->role,
        ])]);
    }

    public function changeRole(Request $request, League $league, User $user): Response
    {
        Gate::authorize('changeRoles', $league);
        $data = $request->validate(['role' => ['required', Rule::in(['OWNER', 'ADMIN', 'MEMBER'])]]);
        abort_unless($league->hasMember($user), 404);
        if ($user->id === $league->owner_id) {
            throw ValidationException::withMessages(['role' => 'O dono só deixa de ser dono passando a liga para outro membro.']);
        }
        $this->leagues->changeRole($league, $user, $data['role']);

        return response()->noContent();
    }

    public function removeMember(League $league, User $user): Response
    {
        abort_unless($league->hasMember($user), 404);
        Gate::authorize('removeMember', [$league, $user]);
        $this->leagues->remove($league, $user);

        return response()->noContent();
    }

    /** Liga privada para quem não participa: 404, como se não existisse. */
    private function authorizeView(League $league): void
    {
        abort_unless(Gate::allows('view', $league), 404);
    }

    /**
     * Ranking da liga: ?board=fantasy|patrimony|shotcall, ?period=round|month|season, ?round=N.
     * Só os quadros das competições que a liga disputa.
     */
    public function standings(Request $request, League $league): JsonResponse
    {
        $this->authorizeView($league);
        $me = $request->user();

        $boards = array_values(array_filter(
            LeagueRanking::BOARDS,
            fn (string $b) => in_array($b === 'shotcall' ? 'SHOTCALL' : 'FANTASY', $league->competitions, true),
        ));
        $board = in_array($request->query('board'), $boards, true) ? $request->query('board') : $boards[0];
        $period = $board === 'patrimony' ? 'round'
            : (in_array($request->query('period'), LeagueRanking::PERIODS, true) ? $request->query('period') : 'season');

        $meta = ['boards' => $boards, 'board' => $board, 'period' => $period, 'round' => null, 'rounds' => [], 'me' => null, 'total' => 0];
        // Liga encerrada: o ranking fica parado na última rodada apurada quando ela terminou, a
        // mesma que LeagueSeasonCloser usou para os troféus. Sem parcial.
        $current = $league->finished_at
            ? Round::where('market_status', Round::SETTLED)->where('starts_at', '<=', $league->finished_at)->orderByDesc('starts_at')->first()
            : Round::current() ?? Round::orderByDesc('starts_at')->first();
        if (! $current) {
            return response()->json(['data' => [], 'meta' => $meta]);
        }

        $rounds = Round::where('season_id', $current->season_id)->where('number', '<=', $current->number)->orderBy('number')->pluck('number');
        $chosen = (int) $request->query('round');
        $anchor = $rounds->contains($chosen)
            ? Round::where('season_id', $current->season_id)->where('number', $chosen)->first()
            : ($board === 'patrimony' || $league->finished_at ? $current : $this->ranking->defaultRound($board));

        $rows = $this->ranking->board($league, $board, $period, $anchor);
        $mine = $rows->firstWhere('user_id', $me->id);
        $shown = $rows->take(self::RANKING_ROWS);
        $users = User::whereIn('id', $shown->pluck('user_id')->push($me->id))->withEquipped()->get()->keyBy('id');

        $present = fn (array $row) => [
            'rank' => $row['rank'], 'move' => $row['move'], 'score' => $row['score'],
            'is_me' => $row['user_id'] === $me->id,
            'user' => (new UserResource($users[$row['user_id']]))->resolve($request),
        ];

        return response()->json([
            'data' => $shown->map($present)->values(),
            'meta' => [
                ...$meta,
                'round' => [
                    'number' => $anchor->number, 'status' => $anchor->market_status, 'month' => $this->ranking->monthLabel($anchor),
                    // Pontos da rodada em andamento são parciais; com jogo rolando, a tela se atualiza sozinha.
                    'provisional' => $board !== 'patrimony' && LivePartials::inProgress($anchor),
                    'live' => $board !== 'patrimony' && LivePartials::inProgress($anchor) && $this->live->hasLiveGames($anchor),
                ],
                'rounds' => $rounds,
                'me' => $mine ? $present($mine) : null,
                'total' => $rows->count(),
            ],
        ]);
    }
}
