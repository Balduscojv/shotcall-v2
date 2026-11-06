<?php

// Regras das missões secretas. Ajuste aqui sem mexer em código.
return [

    // Clutch: o cestinha do usuário fez a última cesta de quadra (lance livre não conta) e ela
    // foi decisiva: nos últimos minutos do 4º quarto ou da prorrogação, com o jogo apertado,
    // e o time dele venceu.
    'clutch' => [
        'window_seconds' => 120,   // últimos 2:00 do período
        'min_period' => 4,         // 4º quarto ou prorrogação
        'max_margin' => 5,         // diferença no placar logo depois da cesta
    ],
];
