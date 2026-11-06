<?php

use App\Services\ShotCalls\AccuracyCalculator;
use App\Services\ShotCalls\GameShotCallSettler;
use App\Services\ShotCalls\TierPolicy;

it('só mostra a taxa depois de 10 jogos apurados', function () {
    $calc = new AccuracyCalculator();

    expect($calc->rate(array_fill(0, 9, ['correct' => 3, 'total' => 3]))['rate'])->toBeNull()
        ->and($calc->rate(array_fill(0, 10, ['correct' => 3, 'total' => 3]))['rate'])->toBe(1.0);
});

it('usa só os últimos 20 jogos', function () {
    $recent = array_fill(0, 20, ['correct' => 3, 'total' => 3]);
    $old = array_fill(0, 30, ['correct' => 0, 'total' => 3]);

    expect((new AccuracyCalculator())->rate([...$recent, ...$old])['rate'])->toBe(1.0);
});

it('define os níveis a partir de 70%', function () {
    $tiers = new TierPolicy();

    expect($tiers->tier(0.69))->toBeNull()
        ->and($tiers->tier(0.7))->toBe(TierPolicy::SILVER)
        ->and($tiers->tier(0.8))->toBe(TierPolicy::GOLD)
        ->and($tiers->tier(0.9))->toBe(TierPolicy::DIAMOND)
        ->and($tiers->tier(1.0, windowFull: false))->toBe(TierPolicy::DIAMOND)
        ->and($tiers->tier(1.0))->toBe(TierPolicy::PERFECT);
});

it('apura vencedor, margem e MVP com empate valendo', function () {
    $result = (new GameShotCallSettler())->settle(
        ['winner_team_id' => 1, 'margin_band' => 'M16_PLUS', 'mvp_player_id' => 7],
        ['winner_team_id' => 1, 'margin' => 17, 'mvp_player_ids' => [7, 8]],
    );

    expect($result['correct'])->toBe(3)->and($result['points'])->toBe(6);
});

it('classifica a margem nas quatro faixas', function () {
    expect(GameShotCallSettler::marginBand(5))->toBe('M1_5')
        ->and(GameShotCallSettler::marginBand(-6))->toBe('M6_10')
        ->and(GameShotCallSettler::marginBand(15))->toBe('M11_15')
        ->and(GameShotCallSettler::marginBand(16))->toBe('M16_PLUS');
});
