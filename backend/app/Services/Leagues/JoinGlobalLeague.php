<?php

namespace App\Services\Leagues;

use App\Models\League;
use App\Models\Round;
use App\Models\User;

/** Todo usuário novo entra na Liga Global e pontua a partir da rodada atual. */
class JoinGlobalLeague
{
    public function __invoke(User $user): void
    {
        $global = League::where('is_global', true)->first();

        if (! $global || $global->hasMember($user)) {
            return;
        }

        $global->members()->attach($user->id, [
            'role' => 'MEMBER',
            'joined_round_number' => Round::current()?->number,
        ]);
    }
}
