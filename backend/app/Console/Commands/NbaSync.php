<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Services\Nba\GameSync;
use App\Services\Nba\RosterSync;
use App\Services\Nba\ScheduleSync;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Sincroniza na mão. Sem opções: elencos, calendário e depois todo jogo que já começou e não
 * tem resultado salvo (útil na primeira vez e depois de um tempo sem o agendador rodando).
 */
#[Signature('nba:sync {--schedule : só o calendário} {--rosters : só os elencos} {--game= : só este jogo} {--refresh : refaz também os jogos já encerrados}')]
#[Description('Puxa elencos, calendário e jogos da NBA da fonte configurada (NBA_PROVIDER)')]
class NbaSync extends Command
{
    public function handle(ScheduleSync $schedule, GameSync $games, RosterSync $rosters): int
    {
        if ($id = $this->option('game')) {
            $game = Game::find($id);
            if ($game === null) {
                $this->error('Jogo não encontrado. Rode nba:sync --schedule antes.');

                return self::FAILURE;
            }
            $games->sync($game);
            $this->info("{$id}: {$game->status} {$game->away_score} x {$game->home_score}");

            return self::SUCCESS;
        }

        $this->info($rosters->run().' jogador(es) nos elencos.');
        if ($this->option('rosters')) {
            return self::SUCCESS;
        }

        $this->info($schedule->run().' jogo(s) no calendário.');
        if ($this->option('schedule')) {
            return self::SUCCESS;
        }

        $statuses = $this->option('refresh') ? ['SCHEDULED', 'LIVE', 'FINAL'] : ['SCHEDULED', 'LIVE'];
        $pending = Game::whereIn('status', $statuses)->where('start_time', '<=', now())->orderBy('start_time')->get();
        $this->withProgressBar($pending, fn (Game $game) => $games->sync($game));
        $this->newLine();
        $this->info($pending->count().' jogo(s) atualizado(s).');

        return self::SUCCESS;
    }
}
