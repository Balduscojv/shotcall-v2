<?php

namespace App\Services\ShotCalls;

/**
 * Apura um shot call de jogo.
 * Mercados: vencedor (1 ponto), margem (2), MVP do jogo (3).
 * MVP do jogo = maior pontuação fantasy da partida; empate conta como acerto.
 */
final class GameShotCallSettler
{
    public const POINTS = ['winner' => 1, 'margin' => 2, 'mvp' => 3];

    public static function marginBand(int $margin): string
    {
        $m = abs($margin);

        return match (true) {
            $m <= 5 => 'M1_5',
            $m <= 10 => 'M6_10',
            $m <= 15 => 'M11_15',
            default => 'M16_PLUS',
        };
    }

    /**
     * @param array{winner_team_id: int, margin_band: string, mvp_player_id: int} $call
     * @param array{winner_team_id: int, margin: int, mvp_player_ids: list<int>} $result
     * @return array{correct: int, total: int, points: int, markets: array<string, bool>}
     */
    public function settle(array $call, array $result): array
    {
        $markets = [
            'winner' => $call['winner_team_id'] === $result['winner_team_id'],
            'margin' => $call['margin_band'] === self::marginBand($result['margin']),
            'mvp' => in_array($call['mvp_player_id'], $result['mvp_player_ids'], true),
        ];

        $points = 0;
        foreach ($markets as $market => $hit) {
            $points += $hit ? self::POINTS[$market] : 0;
        }

        return [
            'correct' => count(array_filter($markets)),
            'total' => count($markets),
            'points' => $points,
            'markets' => $markets,
        ];
    }
}
