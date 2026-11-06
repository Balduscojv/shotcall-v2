<?php

namespace App\Services\Missions\Conditions;

use App\Models\FantasyTeam;
use App\Models\Mission;
use App\Models\Round;
use App\Services\Missions\MissionRunner;

/** O time do fantasy fez pelo menos N pontos na rodada (já com o boost dos palpites). */
class RoundPointsCondition implements Condition
{
    public function key(): string
    {
        return 'round_points';
    }

    public function label(): string
    {
        return 'Pontos do time na rodada';
    }

    public function description(): string
    {
        return 'O time do fantasy fechou a rodada com pelo menos N pontos, já contando o boost dos palpites.';
    }

    public function params(): array
    {
        return [
            ['name' => 'min_points', 'label' => 'Pontos na rodada (mínimo)', 'min' => 1, 'max' => 2000, 'default' => 250],
        ];
    }

    public function events(): array
    {
        return [MissionRunner::ROUND_SETTLED];
    }

    public function achievers(Mission $mission, string $event, mixed $subject): array
    {
        /** @var Round $subject */
        $reason = "Missão {$mission->name} · Rodada {$subject->number}";

        return FantasyTeam::where('round_id', $subject->id)
            ->where('points', '>=', $mission->param('min_points', 250))
            ->pluck('user_id')
            ->mapWithKeys(fn (int $id) => [$id => $reason])
            ->all();
    }
}
