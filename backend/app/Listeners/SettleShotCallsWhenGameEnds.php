<?php

namespace App\Listeners;

use App\Events\GameFinished;
use App\Services\Fantasy\GameScoring;
use App\Services\ShotCalls\ShotCallService;
use Illuminate\Contracts\Queue\ShouldQueue;

/** Fim de jogo: salva a pontuação fantasy (que define o MVP do jogo) e apura os palpites. */
class SettleShotCallsWhenGameEnds implements ShouldQueue
{
    public function __construct(private GameScoring $scoring, private ShotCallService $shotCalls) {}

    public function handle(GameFinished $event): void
    {
        $game = $event->game->fresh();
        $this->scoring->save($game);
        $this->shotCalls->settle($game);
    }
}
