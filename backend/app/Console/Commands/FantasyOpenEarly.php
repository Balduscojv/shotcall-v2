<?php

namespace App\Console\Commands;

use App\Models\Round;
use App\Services\Fantasy\FantasyMarket;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Exceção: abre o mercado da rodada seguinte enquanto esta ainda rola, com preços provisórios.
 * Na apuração desta rodada, os preços e os times já salvos da seguinte são recalculados.
 */
#[Signature('fantasy:open-early {round : número da rodada em andamento (fechada)}')]
#[Description('Abre o mercado da próxima rodada antes da apuração, com preços provisórios')]
class FantasyOpenEarly extends Command
{
    public function handle(FantasyMarket $market): int
    {
        $round = Round::where('number', (int) $this->argument('round'))->orderByDesc('starts_at')->first();
        if ($round === null || $round->market_status !== Round::LOCKED) {
            $this->error('A rodada precisa existir e estar com o mercado fechado (LOCKED).');

            return self::FAILURE;
        }

        $next = $round->next();
        if ($next === null || $next->market_status !== Round::UPCOMING) {
            $this->error('A rodada seguinte não existe ou o mercado dela já abriu.');

            return self::FAILURE;
        }

        $market->openNextEarly($round);
        $this->info("Rodada {$next->number} aberta com preços provisórios. Fecha em "
            .($market->lockAt($next)?->setTimezone('America/Sao_Paulo')->format('d/m H:i') ?? '—').' (Brasília).');

        return self::SUCCESS;
    }
}
