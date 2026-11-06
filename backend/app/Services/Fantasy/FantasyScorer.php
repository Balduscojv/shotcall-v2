<?php

namespace App\Services\Fantasy;

/**
 * Calcula a pontuação fantasy de um jogador em um jogo.
 * Classe pura: recebe os pesos de config('fantasy.scoring').
 */
final class FantasyScorer
{
    public function __construct(private readonly array $w) {}

    /** @return array{total: float, breakdown: array<string, float>} */
    public function score(StatLine $s): array
    {
        $pm = max(-$this->w['plus_minus_cap'], min($this->w['plus_minus_cap'], $s->plusMinus * $this->w['plus_minus_per_point']));

        $breakdown = [
            'pts' => $s->pts * $this->w['pts'],
            'reb' => $s->reb * $this->w['reb'],
            'ast' => $s->ast * $this->w['ast'],
            'stl' => $s->stl * $this->w['stl'],
            'blk' => $s->blk * $this->w['blk'],
            'fg3m_bonus' => $s->fg3m * $this->w['fg3m_bonus'],
            'fg_missed' => max(0, $s->fga - $s->fgm) * $this->w['fg_missed'],
            'ft_missed' => max(0, $s->fta - $s->ftm) * $this->w['ft_missed'],
            'tov' => $s->tov * $this->w['tov'],
            'pf' => $s->pf * $this->w['pf'],
            'tech' => $s->tech * $this->w['tech'],
            'flagrant' => $s->flagrant * $this->w['flagrant'],
            'multi' => $this->multiBonus($s),
            'impact' => $pm,
        ];

        $breakdown = array_map(fn (float $v) => round($v, 2), $breakdown);

        return ['total' => round(array_sum($breakdown), 2), 'breakdown' => $breakdown];
    }

    private function multiBonus(StatLine $s): float
    {
        $tens = count(array_filter([$s->pts, $s->reb, $s->ast, $s->stl, $s->blk], fn (int $v) => $v >= 10));

        return match (true) {
            $tens >= 3 => $this->w['triple_double'],
            $tens === 2 => $this->w['double_double'],
            default => 0.0,
        };
    }
}
