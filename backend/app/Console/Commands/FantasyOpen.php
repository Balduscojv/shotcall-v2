<?php

namespace App\Console\Commands;

use App\Models\Round;
use App\Services\Fantasy\FantasyMarket;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Abre o mercado de uma rodada na mão (a primeira da temporada, ou para corrigir). Depois disso,
 * a apuração de cada rodada abre a seguinte sozinha. Rodadas anteriores que ainda estavam
 * abertas ficam travadas.
 */
#[Signature('fantasy:open {round : número da rodada na temporada atual}')]
#[Description('Abre o mercado do fantasy numa rodada, com preço para todo jogador ativo')]
class FantasyOpen extends Command
{
    public function handle(FantasyMarket $market): int
    {
        $round = Round::whereHas('season', fn ($q) => $q->latest('starts_at'))
            ->where('number', (int) $this->argument('round'))->latest('starts_at')->first();
        if ($round === null) {
            $this->error('Rodada não encontrada.');

            return self::FAILURE;
        }
        if (in_array($round->market_status, [Round::SETTLING, Round::SETTLED], true)) {
            $this->error("A rodada {$round->number} já foi apurada.");

            return self::FAILURE;
        }

        Round::where('season_id', $round->season_id)->where('starts_at', '<', $round->starts_at)
            ->where('market_status', Round::OPEN)->update(['market_status' => Round::LOCKED]);
        $priced = $market->open($round);
        $this->info("Rodada {$round->number} aberta: {$priced} preço(s) novo(s). Fecha em ".($market->lockAt($round)?->setTimezone('America/Sao_Paulo')->format('d/m H:i') ?? '—').' (Brasília).');

        return self::SUCCESS;
    }
}
