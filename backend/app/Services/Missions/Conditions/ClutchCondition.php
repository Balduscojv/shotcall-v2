<?php

namespace App\Services\Missions\Conditions;

use App\Models\FantasyTeam;
use App\Models\Game;
use App\Models\GameAction;
use App\Models\Mission;
use App\Services\Missions\MissionRunner;

/**
 * Clutch: o cestinha do usuário na rodada fez a última cesta de quadra do jogo (lance livre
 * não conta), nos segundos finais do 4º quarto ou da prorrogação, com o placar apertado logo
 * depois dela, e o time dele venceu.
 */
class ClutchCondition implements Condition
{
    public function key(): string
    {
        return 'clutch';
    }

    public function label(): string
    {
        return 'Cesta decisiva do cestinha';
    }

    public function description(): string
    {
        return 'O cestinha escalado fez a última cesta de quadra do jogo, no fim do jogo, com o placar apertado, e o time dele venceu.';
    }

    public function params(): array
    {
        return [
            ['name' => 'window_seconds', 'label' => 'Segundos finais do período', 'help' => 'A cesta precisa sair nos últimos X segundos.', 'min' => 5, 'max' => 720, 'default' => 120],
            ['name' => 'min_period', 'label' => 'A partir do período', 'help' => '4 = 4º quarto; 5 = prorrogação.', 'min' => 1, 'max' => 10, 'default' => 4],
            ['name' => 'max_margin', 'label' => 'Diferença máxima no placar', 'help' => 'Logo depois da cesta.', 'min' => 1, 'max' => 50, 'default' => 5],
        ];
    }

    public function events(): array
    {
        return [MissionRunner::GAME_FINISHED];
    }

    public function achievers(Mission $mission, string $event, mixed $subject): array
    {
        /** @var Game $subject */
        $shot = $this->decisiveLastBasket($subject, $mission);
        if ($shot === null) {
            return [];
        }

        $subject->loadMissing(['home', 'away']);
        $reason = "Missão {$mission->name} · {$subject->away->tricode} @ {$subject->home->tricode}";

        return FantasyTeam::where('round_id', $subject->round_id)
            ->where('cestinha_id', $shot->player_id)
            ->pluck('user_id')
            ->mapWithKeys(fn (int $id) => [$id => $reason])
            ->all();
    }

    /** A última cesta de quadra do jogo, se ela foi decisiva. */
    public function decisiveLastBasket(Game $game, Mission $mission): ?GameAction
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

        $inWindow = $shot->period >= $mission->param('min_period', 4)
            && self::seconds($shot->clock) <= $mission->param('window_seconds', 120);
        $close = abs($shot->score_home - $shot->score_away) <= $mission->param('max_margin', 5);

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
