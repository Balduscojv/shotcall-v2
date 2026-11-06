<?php

namespace App\Listeners;

use App\Events\GameFinished;
use App\Services\Missions\MissionRunner;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Fim de jogo: avalia as missões secretas (ex.: Clutch) fora do acompanhamento ao vivo. */
class RunMissionsWhenGameEnds implements ShouldQueue
{
    public function __construct(private MissionRunner $missions) {}

    public function handle(GameFinished $event): void
    {
        $this->missions->gameFinished($event->game->fresh());
    }
}
