<?php

namespace App\Services\ShotCalls;

/**
 * Taxa de acerto do shot call de jogo: últimos N jogos apurados, mínimo M para contar.
 * $settled: lista de ['correct' => int, 'total' => int], do mais recente para o mais antigo.
 */
final class AccuracyCalculator
{
    public function __construct(
        private readonly int $window = 20,
        private readonly int $minimum = 10,
    ) {}

    /** @return array{rate: ?float, counted: int} */
    public function rate(array $settled): array
    {
        $slice = array_slice($settled, 0, $this->window);
        $counted = count($slice);

        if ($counted < $this->minimum) {
            return ['rate' => null, 'counted' => $counted];
        }

        $total = array_sum(array_column($slice, 'total'));
        $correct = array_sum(array_column($slice, 'correct'));

        return ['rate' => $total > 0 ? round($correct / $total, 4) : null, 'counted' => $counted];
    }

    public function windowIsFull(int $counted): bool
    {
        return $counted >= $this->window;
    }
}
