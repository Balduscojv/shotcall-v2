<?php

namespace App\Console\Commands;

use App\Models\Season;
use App\Services\Leagues\LeagueSeasonCloser;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Encerra as ligas de uma temporada à mão. Normalmente acontece sozinho, na apuração da última
 * rodada (RoundSettlement). Liga já finalizada não muda.
 */
#[Signature('leagues:finish {season : rótulo da temporada, ex.: 2026-27}')]
#[Description('Finaliza as ligas da temporada: ranking final, troféus e prêmios de liga')]
class FinishLeagues extends Command
{
    public function handle(LeagueSeasonCloser $closer): int
    {
        $season = Season::where('label', $this->argument('season'))->first();
        if (! $season) {
            $this->error("Temporada {$this->argument('season')} não encontrada.");

            return self::FAILURE;
        }

        $this->info($closer->closeSeason($season).' liga(s) finalizada(s).');

        return self::SUCCESS;
    }
}
