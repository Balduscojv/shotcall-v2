<?php

use App\Services\Fantasy\LineupValidator;

beforeEach(function () {
    $this->validator = new LineupValidator(['G' => 2, 'F' => 2, 'C' => 1]);
    $this->lineup = [
        ['id' => 1, 'position' => 'G', 'price' => 20.0],
        ['id' => 2, 'position' => 'G', 'price' => 20.0],
        ['id' => 3, 'position' => 'F', 'price' => 20.0],
        ['id' => 4, 'position' => 'F', 'price' => 20.0],
        ['id' => 5, 'position' => 'C', 'price' => 20.0],
    ];
});

it('aceita uma escalação válida', function () {
    expect($this->validator->validate($this->lineup, 3, 100.0, true))->toBe([]);
});

it('recusa com o mercado fechado', function () {
    expect($this->validator->validate($this->lineup, 3, 100.0, false))->toContain('market_closed');
});

it('recusa quando não cabe no patrimônio', function () {
    expect($this->validator->validate($this->lineup, 3, 99.99, true))->toContain('insufficient_balance');
});

it('exige que o Cestinha esteja entre os titulares', function () {
    expect($this->validator->validate($this->lineup, 99, 100.0, true))->toContain('cestinha_not_in_lineup');
});

it('exige 2 armadores, 2 alas e 1 pivô', function () {
    $wrong = $this->lineup;
    $wrong[4]['position'] = 'G';

    expect($this->validator->validate($wrong, 3, 100.0, true))
        ->toContain('position_G_needs_2')
        ->toContain('position_C_needs_1');
});
