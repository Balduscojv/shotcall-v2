<?php

namespace App\Events;

use App\Models\Game;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Um jogo acabou de virar FINAL. Disparado uma vez só, pelo GameSync. */
class GameFinished
{
    use Dispatchable, SerializesModels;

    public function __construct(public Game $game) {}
}
