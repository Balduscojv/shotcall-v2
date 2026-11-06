<?php

namespace App\Services\Missions\Conditions;

use App\Models\GameShotCall;
use App\Models\Mission;
use App\Models\Round;
use App\Services\Missions\MissionRunner;

/** Cravou o palpite inteiro (vencedor, margem e MVP) em pelo menos N jogos da rodada. */
class PerfectShotCallsCondition implements Condition
{
    /** Mercados de um palpite de jogo: vencedor, margem e MVP. */
    private const MARKETS = 3;

    public function key(): string
    {
        return 'perfect_shot_calls';
    }

    public function label(): string
    {
        return 'Palpites perfeitos na rodada';
    }

    public function description(): string
    {
        return 'Acertou vencedor, margem e MVP no mesmo palpite em pelo menos N jogos da rodada.';
    }

    public function params(): array
    {
        return [
            ['name' => 'min_games', 'label' => 'Jogos com palpite perfeito (mínimo)', 'min' => 1, 'max' => 50, 'default' => 3],
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

        return GameShotCall::where('status', 'SETTLED')
            ->where('correct', self::MARKETS)
            ->whereHas('game', fn ($q) => $q->where('round_id', $subject->id))
            ->groupBy('user_id')
            ->havingRaw('count(*) >= ?', [$mission->param('min_games', 3)])
            ->pluck('user_id')
            ->mapWithKeys(fn (int $id) => [$id => $reason])
            ->all();
    }
}
