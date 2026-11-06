<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Um palpite de jogo no histórico. Espera game.home, game.away, winner e mvp carregados. @mixin \App\Models\GameShotCall */
class ShotCallHistoryResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $game = $this->game;

        return [
            'id' => $this->id,
            'status' => $this->status,
            'correct' => $this->correct,
            'points' => $this->points,
            'settled_at' => $this->settled_at?->toIso8601String(),
            'pick' => [
                'winner' => $this->winner?->tricode,
                'margin_band' => $this->margin_band,
                'mvp' => $this->mvp?->full_name,
            ],
            'game' => [
                'id' => $game->id,
                'start_time' => $game->start_time?->toIso8601String(),
                'status' => $game->status,
                'home' => self::team($game->home, $game->home_score),
                'away' => self::team($game->away, $game->away_score),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private static function team($team, int $score): array
    {
        return ['tricode' => $team->tricode, 'color' => $team->primary_color, 'score' => $score];
    }
}
