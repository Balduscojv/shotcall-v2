<?php

// Equilíbrio do fantasy. Ajuste aqui sem mexer em código.
return [

    'starting_balance' => 100.0,

    // Titulares por posição. Sem banco de reservas.
    'lineup' => ['G' => 2, 'F' => 2, 'C' => 1],

    // O Cestinha escolhido pelo usuário pontua em dobro.
    'cestinha_multiplier' => 2.0,

    // Pontos fantasy por jogo.
    'scoring' => [
        'pts' => 1.0,
        'reb' => 1.2,
        'ast' => 1.5,
        'stl' => 3.0,
        'blk' => 3.0,
        'fg3m_bonus' => 0.5,
        'fg_missed' => -0.5,
        'ft_missed' => -0.5,
        'tov' => -1.0,
        'pf' => -0.5,
        'tech' => -2.0,
        'flagrant' => -3.0,
        'double_double' => 3.0,
        'triple_double' => 5.0, // substitui o duplo-duplo
        'plus_minus_per_point' => 0.1,
        'plus_minus_cap' => 3.0,
    ],

    // Preço e valorização.
    'pricing' => [
        'min' => 1.0,
        'initial_min' => 2.0,
        'initial_max' => 25.0,
        'rookie' => 5.0,
        'points_per_coin' => 2.0,   // "mínimo para valorizar" = preço x 2 por jogo
        'sensitivity' => 0.1,
        'max_variation_pct' => 0.15,
    ],

    'market' => [
        'close_minutes_before_first_game' => 0,
        'settle_target_minutes' => 15,
    ],
];
