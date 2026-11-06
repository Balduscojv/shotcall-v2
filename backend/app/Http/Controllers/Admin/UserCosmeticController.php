<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CosmeticResource;
use App\Http\Resources\UserResource;
use App\Models\Cosmetic;
use App\Models\User;
use App\Services\Cosmetics\AwardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Moderação de prêmios de um usuário — só admin (rota protegida por role:admin). */
class UserCosmeticController extends Controller
{
    public function __construct(private AwardService $awards) {}

    /** Catálogo inteiro, marcando o que o usuário já tem e o que está usando. */
    public function index(User $user): JsonResponse
    {
        return response()->json([
            'data' => CosmeticResource::collection($this->awards->catalogFor($user)),
            'user' => new UserResource($user->load(['equippedBanner', 'equippedFrame', 'equippedTitle'])),
        ]);
    }

    /** POST { key, reason? } — libera o prêmio para o usuário. */
    public function store(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'key' => ['required', 'string', 'exists:cosmetics,key'],
            'reason' => ['nullable', 'string', 'max:120'],
        ], [
            'key.required' => 'Escolha um prêmio.',
            'key.exists' => 'Prêmio não encontrado.',
            'reason.max' => 'O motivo pode ter até 120 caracteres.',
        ]);

        $cosmetic = Cosmetic::where('key', $data['key'])->firstOrFail();
        $reason = trim((string) ($data['reason'] ?? '')) ?: 'Liberado pela moderação';

        if (! $this->awards->grant($user, $cosmetic, $reason, $request->user())) {
            return response()->json(['message' => 'Esse usuário já tem esse prêmio.', 'errors' => ['key' => ['Esse usuário já tem esse prêmio.']]], 422);
        }

        return response()->json(['message' => "{$cosmetic->name} liberado para @{$user->handle}."], 201);
    }

    /** DELETE — tira o prêmio do usuário (e desequipa, se estava em uso). */
    public function destroy(User $user, Cosmetic $cosmetic): JsonResponse
    {
        if (! $this->awards->revoke($user, $cosmetic)) {
            return response()->json(['message' => 'Esse usuário não tem esse prêmio.'], 404);
        }

        return response()->json(['message' => "{$cosmetic->name} removido de @{$user->handle}."]);
    }
}
