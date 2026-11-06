<?php

namespace App\Http\Resources;

use App\Models\Game;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Jogo no calendário: times, horário, status e placar. O relógio só vem com o jogo ao vivo.
 *
 * @mixin Game
 */
class GameResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'phase' => $this->phase,
            'start_time' => $this->start_time->toIso8601String(),
            'status' => $this->status,
            'period' => $this->period,
            'clock' => $this->status === 'LIVE' ? $this->clock : null,
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'arena' => $this->arena,
            'national_tv' => $this->national_tv,
            'round' => $this->round_id,
            // Só na lista do calendário: o resumo do meu palpite neste jogo.
            'my_shot_call' => $this->when($this->resource->hasAttribute('my_shot_call'), fn () => $this->resource->getAttribute('my_shot_call')),
            'home' => self::team($this->home),
            'away' => self::team($this->away),
        ];
    }

    /** @return array<string, mixed> */
    public static function team(Team $team): array
    {
        return [
            'id' => $team->id,
            'tricode' => $team->tricode,
            'city' => $team->city,
            'name' => $team->name,
            'primary_color' => $team->primary_color,
            'secondary_color' => $team->secondary_color,
        ];
    }
}
