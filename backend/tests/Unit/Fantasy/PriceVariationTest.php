<?php

use App\Services\Fantasy\PriceVariation;

beforeEach(function () {
    $this->prices = new PriceVariation((require __DIR__.'/../../../config/fantasy.php')['pricing']);
});

it('valoriza quem passa do mínimo e desvaloriza quem fica abaixo', function () {
    expect($this->prices->next(10.0, 1, 30.0)['price'])->toBe(11.0)
        ->and($this->prices->next(20.0, 1, 32.0)['price'])->toBe(19.2);
});

it('limita a variação a 15% do preço', function () {
    expect($this->prices->next(10.0, 2, 200.0)['price'])->toBe(11.5);
});

it('não mexe no preço de quem não jogou', function () {
    expect($this->prices->next(10.0, 0, 0.0))->toBe(['price' => 10.0, 'variation' => 0.0]);
});

it('calcula o preço inicial dentro da faixa', function () {
    expect($this->prices->initial(60.0))->toBe(25.0)
        ->and($this->prices->initial(2.0))->toBe(2.0)
        ->and($this->prices->initial(null))->toBe(5.0);
});
