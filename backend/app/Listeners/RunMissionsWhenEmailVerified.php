<?php

namespace App\Listeners;

use App\Services\Missions\MissionRunner;
use Illuminate\Auth\Events\Verified;

/** E-mail confirmado: libera as missões de "confirme seu e-mail". */
class RunMissionsWhenEmailVerified
{
    public function __construct(private MissionRunner $missions) {}

    public function handle(Verified $event): void
    {
        $this->missions->emailVerified($event->user);
    }
}
