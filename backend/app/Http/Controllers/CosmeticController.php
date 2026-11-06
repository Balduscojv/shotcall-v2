<?php

namespace App\Http\Controllers;

use App\Enums\CosmeticSlot;
use App\Http\Resources\CosmeticResource;
use App\Http\Resources\UserResource;
use App\Models\Cosmetic;
use App\Services\Cosmetics\AwardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Coleção de prêmios de quem está logado e o que está em uso. */
class CosmeticController extends Controller
{
    public function __construct(private AwardService $awards) {}

    /** Prêmios que posso equipar (admin vê o catálogo inteiro). */
    public function index(Request $request): AnonymousResourceCollection
    {
        return CosmeticResource::collection($this->awards->usableBy($request->user()));
    }

    /** PUT /me/cosmetics/{slot} { key: string|null } — equipa ou tira o prêmio daquele tipo. */
    public function equip(Request $request, string $slot): JsonResponse
    {
        $slot = CosmeticSlot::fromParam($slot) ?? abort(404);

        $data = $request->validate(['key' => ['present', 'nullable', 'string', 'max:255']], [
            'key.present' => 'Diga qual prêmio usar (ou null para tirar).',
        ]);

        $cosmetic = null;
        if ($data['key'] !== null) {
            $cosmetic = Cosmetic::where('key', $data['key'])->first();
            if ($cosmetic === null) {
                return response()->json(['message' => 'Prêmio não encontrado.', 'errors' => ['key' => ['Prêmio não encontrado.']]], 422);
            }
        }

        $user = $request->user();
        $this->awards->equip($user, $slot, $cosmetic);

        // Status explícito: o Resource devolveria 201 se o model foi criado na mesma execução.
        return (new UserResource($user->load(['equippedBanner', 'equippedFrame', 'equippedTitle'])))->response()->setStatusCode(200);
    }
}
