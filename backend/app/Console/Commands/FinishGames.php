<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\Fantasy\GameScoring;
use App\Services\Missions\MissionRunner;
use App\Services\ShotCalls\ShotCallService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Roda na mão o que acontece no fim de um jogo: pontuação fantasy, apuração dos palpites e
 * missões secretas. Serve para jogos que terminaram sem a fila rodando, ou depois de corrigir
 * dados. Pode repetir: o que já foi apurado ou liberado não muda.
 */
#[Signature('games:finish {game? : id do jogo} {--all : todos os jogos encerrados}')]
#[Description('Pontua, apura palpites e roda missões de jogos encerrados')]
class FinishGames extends Command
{
    public function handle(GameScoring $scoring, ShotCallService $shotCalls, MissionRunner $missions): int
    {
        $games = match (true) {
            (bool) $this->option('all') => Game::where('status', 'FINAL')->orderBy('start_time')->get(),
            $this->argument('game') !== null => Game::whereKey($this->argument('game'))->where('status', 'FINAL')->get(),
            default => collect(),
        };
        if ($games->isEmpty()) {
            $this->error('Nenhum jogo encerrado para processar. Passe o id do jogo ou --all.');

            return self::FAILURE;
        }

        $settled = 0;
        $awards = 0;
        $this->withProgressBar($games, function (Game $game) use ($scoring, $shotCalls, $missions, &$settled, &$awards) {
            $scoring->save($game);
            $settled += $shotCalls->settle($game);
            $awards += $missions->gameFinished($game);
        });
        $this->newLine();
        $this->info("{$games->count()} jogo(s) · {$settled} palpite(s) apurado(s) · {$awards} prêmio(s) liberado(s).");

        return self::SUCCESS;
    }
}
