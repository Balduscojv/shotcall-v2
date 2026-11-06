<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\Missions\MissionRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/** Roda as missões secretas de um jogo encerrado (ex.: depois de corrigir o jogo-a-jogo). */
#[Signature('missions:run {game : id do jogo da NBA}')]
#[Description('Avalia as missões secretas de um jogo encerrado e libera os prêmios')]
class RunGameMissions extends Command
{
    public function handle(MissionRunner $missions): int
    {
        $game = Game::find($this->argument('game'));
        if ($game === null) {
            $this->error('Jogo não encontrado.');

            return self::FAILURE;
        }
        if ($game->status !== 'FINAL') {
            $this->error("O jogo ainda não terminou (status {$game->status}).");

            return self::FAILURE;
        }

        $this->info($missions->gameFinished($game).' prêmio(s) liberado(s).');

        return self::SUCCESS;
    }
}
