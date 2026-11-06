<?php

namespace App\Services\Leagues;

use App\Models\League;
use App\Models\Round;
use App\Models\User;

/**
 * Todo usuário está na Liga Global, sem limite de membros, e pontua a partir da rodada em que
 * chegou. Roda na criação do usuário (User::booted). Só a conta de sistema, dona da liga, fica
 * de fora: ela não joga.
 */
class JoinGlobalLeague
{
    public function __invoke(User $user): void
    {
        $global = League::where('is_global', true)->first();

        if (! $global || $global->owner_id === $user->id || $global->hasMember($user)) {
            return;
        }

        $global->members()->attach($user->id, [
            'role' => 'MEMBER',
            'joined_round_number' => Round::current()?->number,
        ]);
    }
}
