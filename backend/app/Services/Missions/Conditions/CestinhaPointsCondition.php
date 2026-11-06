<?php

namespace App\Services\Missions\Conditions;

use App\Models\FantasyTeam;
use App\Models\Game;
use App\Models\Mission;
use App\Models\PlayerGameStat;
use App\Services\Missions\MissionRunner;

/** O cestinha escalado fez pelo menos N pontos num jogo. */
class CestinhaPointsCondition implements Condition
{
    public function key(): string
    {
        return 'cestinha_points';
    }

    public function label(): string
    {
        return 'Cestinha pontuou muito num jogo';
    }

    public function description(): string
    {
        return 'O cestinha escalado na rodada fez pelo menos N pontos de basquete num único jogo.';
    }

    public function params(): array
    {
        return [
            ['name' => 'min_points', 'label' => 'Pontos no jogo (mínimo)', 'min' => 10, 'max' => 100, 'default' => 40],
        ];
    }

    public function events(): array
    {
        return [MissionRunner::GAME_FINISHED];
    }

    public function achievers(Mission $mission, string $event, mixed $subject): array
    {
        /** @var Game $subject */
        if ($subject->round_id === null) {
            return [];
        }

        $scorers = PlayerGameStat::where('game_id', $subject->id)
            ->where('pts', '>=', $mission->param('min_points', 40))
            ->pluck('player_id');
        if ($scorers->isEmpty()) {
            return [];
        }

        $subject->loadMissing(['home', 'away']);
        $reason = "Missão {$mission->name} · {$subject->away->tricode} @ {$subject->home->tricode}";

        return FantasyTeam::where('round_id', $subject->round_id)
            ->whereIn('cestinha_id', $scorers)
            ->pluck('user_id')
            ->mapWithKeys(fn (int $id) => [$id => $reason])
            ->all();
    }
}
