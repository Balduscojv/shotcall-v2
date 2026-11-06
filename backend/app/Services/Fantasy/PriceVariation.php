<?php

namespace App\Services\Fantasy;

/**
 * Preço inicial e valorização por rodada.
 * Delta = sensibilidade x (média por jogo - pontos por ficha x preço),
 * limitado a +/- X% do preço; sem jogos na rodada, o preço não muda.
 */
final class PriceVariation
{
    public function __construct(private readonly array $p) {}

    public function initial(?float $lastSeasonAverage): float
    {
        if ($lastSeasonAverage === null) {
            return $this->p['rookie'];
        }

        return round(max($this->p['initial_min'], min($this->p['initial_max'], $lastSeasonAverage / 2)), 2);
    }

    public function minToGain(float $price): float
    {
        return round($price * $this->p['points_per_coin'], 2);
    }

    /** @return array{price: float, variation: float} */
    public function next(float $price, int $games, float $roundPoints): array
    {
        if ($games === 0) {
            return ['price' => $price, 'variation' => 0.0];
        }

        $avg = $roundPoints / $games;
        $delta = $this->p['sensitivity'] * ($avg - $this->minToGain($price));
        $cap = $price * $this->p['max_variation_pct'];
        $delta = max(-$cap, min($cap, $delta));

        $new = round(max($this->p['min'], $price + $delta), 2);

        return ['price' => $new, 'variation' => round($new - $price, 2)];
    }
}
