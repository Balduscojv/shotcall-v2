<?php

namespace App\Jobs;

use App\Models\Game;
use App\Services\Nba\GameSync;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Uma atualização de um jogo ao vivo. Único por jogo: se a anterior não acabou, pula. */
class SyncLiveGame implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 14;

    public int $tries = 1;

    public function __construct(public string $gameId) {}

    public function uniqueId(): string
    {
        return $this->gameId;
    }

    public function handle(GameSync $sync): void
    {
        $game = Game::find($this->gameId);
        if ($game !== null) {
            $sync->sync($game);
        }
    }
}
