<?php

namespace App\Services\ShotCalls;

/** Nível alcançado a partir da taxa de acerto. */
final class TierPolicy
{
    public const SILVER = 'SILVER';
    public const GOLD = 'GOLD';
    public const DIAMOND = 'DIAMOND';
    public const PERFECT = 'PERFECT';

    /** Para o modo jogo, "Perfeito" exige a janela de 20 jogos cheia. */
    public function tier(?float $rate, bool $windowFull = true): ?string
    {
        return match (true) {
            $rate === null => null,
            $rate >= 1.0 && $windowFull => self::PERFECT,
            $rate >= 0.9 => self::DIAMOND,
            $rate >= 0.8 => self::GOLD,
            $rate >= 0.7 => self::SILVER,
            default => null,
        };
    }
}
