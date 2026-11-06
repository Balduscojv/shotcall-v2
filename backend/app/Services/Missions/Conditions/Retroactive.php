<?php

namespace App\Services\Missions\Conditions;

use App\Models\Mission;

/**
 * Condição que dá para conferir a qualquer momento, sem esperar um evento (ex.: contagem de
 * títulos). Quando a moderação cria ou liga a missão, quem já cumpre ganha na hora.
 */
interface Retroactive
{
    /** @return array<int, string> id do usuário => motivo */
    public function currentAchievers(Mission $mission): array;
}
