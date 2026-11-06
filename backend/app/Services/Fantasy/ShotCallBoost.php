<?php

namespace App\Services\Fantasy;

use App\Models\GameShotCall;
use App\Models\Round;
use Illuminate\Support\Collection;

/**
 * Boost de palpites no fantasy: cada acerto de shot call num jogo da rodada vale um tanto de
 * multiplicador, pelo peso do mercado (config fantasy.shotcall_boost: vencedor +1%, margem +2%,
 * MVP do jogo +3%), até o teto da rodada (+30%). Só contam palpites já apurados.
 */
class ShotCallBoost
{
    /**
     * @return array{boost: float, raw: float, cap: float, calls: int, hits: array{winner: int, margin: int, mvp: int}}
     */
    public function forUser(int $userId, Round $round): array
    {
        return $this->fromCalls($this->settledCalls($round)->where('user_id', $userId)->get(['markets']));
    }

    /**
     * Boost de todo mundo que palpitou na rodada, de uma vez (parciais das ligas).
     *
     * @return Collection<int, float> boost por user_id
     */
    public function forRound(Round $round): Collection
    {
        return $this->settledCalls($round)->get(['user_id', 'markets'])
            ->groupBy('user_id')
            ->map(fn (Collection $calls) => $this->fromCalls($calls)['boost']);
    }

    /** Pontos com o boost aplicado. */
    public static function apply(float $base, float $boost): float
    {
        return round($base * (1 + $boost), 2);
    }

    private function settledCalls(Round $round)
    {
        return GameShotCall::where('status', 'SETTLED')->whereHas('game', fn ($q) => $q->where('round_id', $round->id));
    }

    /** @param Collection<int, GameShotCall> $calls */
    private function fromCalls(Collection $calls): array
    {
        $weights = config('fantasy.shotcall_boost.per_market');
        $cap = (float) config('fantasy.shotcall_boost.cap');

        $hits = ['winner' => 0, 'margin' => 0, 'mvp' => 0];
        foreach ($calls as $call) {
            foreach ($hits as $market => $n) {
                $hits[$market] += ($call->markets[$market] ?? false) ? 1 : 0;
            }
        }
        $raw = array_sum(array_map(fn ($m) => $hits[$m] * (float) $weights[$m], array_keys($hits)));

        return [
            'boost' => round(min($cap, $raw), 4),
            'raw' => round($raw, 4),
            'cap' => $cap,
            'calls' => $calls->count(),
            'hits' => $hits,
        ];
    }
}
