<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Services\ShotCalls\ShotCallService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Meu shot call de um jogo. */
class ShotCallController extends Controller
{
    public function __construct(private ShotCallService $shotCalls) {}

    /** PUT /games/{game}/shot-call { winner_team_id, margin_band, mvp_player_id } */
    public function update(Request $request, Game $game): JsonResponse
    {
        $data = $request->validate([
            'winner_team_id' => ['required', 'integer'],
            'margin_band' => ['required', Rule::in(ShotCallService::BANDS)],
            'mvp_player_id' => ['required', 'integer'],
        ], [
            'winner_team_id.required' => 'Escolha quem vence.',
            'margin_band.required' => 'Escolha a margem.',
            'margin_band.in' => 'Margem inválida.',
            'mvp_player_id.required' => 'Escolha o MVP do jogo.',
        ]);

        $call = $this->shotCalls->submit($request->user(), $game, $data);

        return response()->json(['data' => [
            'winner_team_id' => (int) $call->winner_team_id,
            'margin_band' => $call->margin_band,
            'mvp_player_id' => (int) $call->mvp_player_id,
            'status' => $call->status,
        ]]);
    }
}
