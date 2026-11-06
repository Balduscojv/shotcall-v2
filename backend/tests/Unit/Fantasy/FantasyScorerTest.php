<?php

use App\Services\Fantasy\FantasyScorer;
use App\Services\Fantasy\StatLine;

beforeEach(function () {
    $this->scorer = new FantasyScorer((require __DIR__.'/../../../config/fantasy.php')['scoring']);
});

it('reproduz o exemplo do plano: 48,3 pontos', function () {
    $line = new StatLine(pts: 30, reb: 8, ast: 7, stl: 1, blk: 1, tov: 3, pf: 3,
        fgm: 11, fga: 22, fg3m: 3, ftm: 5, fta: 6, plusMinus: 12);

    expect($this->scorer->score($line)['total'])->toBe(48.3);
});

it('dá bônus de duplo-duplo e troca por triplo-duplo', function () {
    $double = $this->scorer->score(new StatLine(pts: 10, reb: 10))['breakdown']['multi'];
    $triple = $this->scorer->score(new StatLine(pts: 10, reb: 10, ast: 10))['breakdown']['multi'];

    expect($double)->toBe(3.0)->and($triple)->toBe(5.0);
});

it('limita o impacto do saldo em quadra a 3 pontos', function () {
    expect($this->scorer->score(new StatLine(plusMinus: 40))['breakdown']['impact'])->toBe(3.0)
        ->and($this->scorer->score(new StatLine(plusMinus: -40))['breakdown']['impact'])->toBe(-3.0);
});

it('pune faltas técnicas e flagrantes', function () {
    expect($this->scorer->score(new StatLine(tech: 1, flagrant: 1))['total'])->toBe(-5.0);
});
