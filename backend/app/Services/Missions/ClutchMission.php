<?php

namespace App\Services\Missions;

use App\Models\FantasyTeam;
use App\Models\Game;
use App\Models\GameAction;
use Illuminate\Support\Collection;

/**
 * Clutch: o jogador que o usuário escolheu como cestinha na rodada fez a última cesta do jogo,
 * e ela foi decisiva (config/missions.php):
 * - cesta de quadra (2 ou 3); lance livre não conta;
 * - nos últimos 2:00 do 4º quarto ou da prorrogação;
 * - placar com diferença de até 5 logo depois dela;
 * - o time de quem arremessou venceu.
 */
class ClutchMission implements Mission
{
    public const TRIGGER = 'MISSION:CLUTCH';

    public function trigger(): string
    {
        return self::TRIGGER;
    }

    public function achieversOf(Game $game): Collection
    {
        $shot = $this->decisiveLastBasket($game);
        if ($shot === null) {
            return collect();
        }

        return FantasyTeam::where('round_id', $game->round_id)
            ->where('cestinha_id', $shot->player_id)
            ->pluck('user_id');
    }

    public function reason(Game $game): string
    {
        $game->loadMissing(['home', 'away']);

        return "Missão Clutch · {$game->away->tricode} @ {$game->home->tricode}";
    }

    /** A última cesta de quadra do jogo, se ela foi decisiva. */
    public function decisiveLastBasket(Game $game): ?GameAction
    {
        if ($game->status !== 'FINAL' || $game->round_id === null) {
            return null;
        }

        /** @var GameAction|null $shot */
        $shot = $game->actions()
            ->where('shot_result', 'MADE')
            ->whereIn('action_type', ['2pt', '3pt'])
            ->orderByDesc('action_number')
            ->first();

        if ($shot === null || $shot->player_id === null || $shot->score_home === null || $shot->score_away === null) {
            return null;
        }

        $rules = config('missions.clutch');
        $inWindow = $shot->period >= $rules['min_period'] && self::seconds($shot->clock) <= $rules['window_seconds'];
        $close = abs($shot->score_home - $shot->score_away) <= $rules['max_margin'];

        $shooterIsHome = (int) $shot->team_id === (int) $game->home_team_id;
        $won = $shooterIsHome ? $game->home_score > $game->away_score : $game->away_score > $game->home_score;

        return $inWindow && $close && $won ? $shot : null;
    }

    /** "01:45" -> 105. Relógio vazio conta como fim do período. */
    private static function seconds(?string $clock): int
    {
        if (! $clock || ! preg_match('/^(\d+):(\d+)/', $clock, $m)) {
            return 0;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }
}
