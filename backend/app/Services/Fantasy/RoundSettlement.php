<?php

namespace App\Services\Fantasy;

use App\Models\FantasyTeam;
use App\Models\Game;
use App\Models\PlayerPrice;
use App\Models\PlayerRoundScore;
use App\Models\Round;
use App\Services\ShotCalls\ShotCallService;
use Illuminate\Support\Facades\DB;

/**
 * Apuração da rodada, quando o último jogo dela termina:
 * 1. pontos de cada time: soma dos 5 (cestinha em dobro) × (1 + boost de palpites);
 * 2. preços da próxima rodada (valorização pela média na rodada);
 * 3. o time segue para a próxima rodada com os mesmos jogadores, e o patrimônio passa a ser
 *    saldo + valor do time nos preços novos;
 * 4. a rodada fecha e o mercado da próxima abre.
 */
class RoundSettlement
{
    public function __construct(
        private PriceVariation $pricing,
        private FantasyMarket $market,
        private FantasyTeamService $teams,
        private ShotCallBoost $boost,
        private GameScoring $scoring,
        private ShotCallService $shotCalls,
    ) {}

    /** Rodadas travadas que já acabaram e não têm jogo pendente. */
    public function settleFinished(): int
    {
        return Round::where('market_status', Round::LOCKED)->where('ends_at', '<', now())->get()
            ->reject(fn (Round $r) => Game::where('round_id', $r->id)->whereIn('status', ['SCHEDULED', 'LIVE'])->exists())
            ->each(fn (Round $r) => $this->settle($r))
            ->count();
    }

    /**
     * O fim de cada jogo roda na fila (pontuação e palpites). Se a rodada for apurada antes de a
     * fila chegar no último jogo, faz aqui o que faltou. Repetir não muda nada.
     */
    private function finishPendingGames(Round $round): void
    {
        Game::where('round_id', $round->id)->where('status', 'FINAL')
            ->where(fn ($q) => $q->whereDoesntHave('scores')->orWhereHas('shotCalls', fn ($c) => $c->whereIn('status', ['OPEN', 'LOCKED'])))
            ->get()
            ->each(function (Game $game) {
                $this->scoring->save($game);
                $this->shotCalls->settle($game);
            });
    }

    public function settle(Round $round): void
    {
        if ($round->market_status !== Round::LOCKED) {
            return;
        }
        $round->update(['market_status' => Round::SETTLING]);
        $next = $round->next();
        $this->finishPendingGames($round);

        DB::transaction(function () use ($round, $next) {
            $scores = PlayerRoundScore::where('round_id', $round->id)->get()->keyBy('player_id');

            // Preços da próxima rodada.
            $newPrices = collect();
            if ($next) {
                foreach (PlayerPrice::where('round_id', $round->id)->get() as $price) {
                    $s = $scores[$price->player_id] ?? null;
                    $r = $this->pricing->next((float) $price->price, (int) ($s->games ?? 0), (float) ($s->points ?? 0));
                    $newPrices[$price->player_id] = $r['price'];
                    PlayerPrice::upsert([[
                        'player_id' => $price->player_id, 'round_id' => $next->id, 'price' => $r['price'],
                        'variation' => $r['variation'], 'min_to_gain' => $this->pricing->minToGain($r['price']),
                    ]], ['player_id', 'round_id'], ['price', 'variation', 'min_to_gain']);
                }
            }

            $points = $scores->map(fn ($s) => ['points' => (float) $s->points, 'games' => (int) $s->games, 'live' => false]);
            FantasyTeam::with('players')->where('round_id', $round->id)->get()->each(function (FantasyTeam $team) use ($points, $next, $newPrices, $round) {
                // Pontos dos 5 (cestinha em dobro) × (1 + boost dos palpites da rodada).
                $base = $this->teams->teamPoints($team, $points);
                $boost = $this->boost->forUser($team->user_id, $round)['boost'];
                $team->update(['base_points' => $base, 'boost' => $boost, 'points' => ShotCallBoost::apply($base, $boost)]);
                if (! $next || FantasyTeam::where('user_id', $team->user_id)->where('round_id', $next->id)->exists()) {
                    return;
                }

                // O time segue: mesmos jogadores, preço de compra = preço novo.
                $value = $team->players->sum(fn ($p) => $newPrices[$p->player_id] ?? (float) $p->bought_at_price);
                $carried = FantasyTeam::create([
                    'user_id' => $team->user_id, 'round_id' => $next->id, 'cestinha_id' => $team->cestinha_id,
                    'balance' => $team->balance, 'patrimony' => round((float) $team->balance + $value, 2),
                ]);
                $carried->players()->insert($team->players->map(fn ($p) => [
                    'fantasy_team_id' => $carried->id, 'slot' => $p->slot, 'player_id' => $p->player_id,
                    'bought_at_price' => $newPrices[$p->player_id] ?? (float) $p->bought_at_price,
                ])->all());
            });

            $round->update(['market_status' => Round::SETTLED]);
        });

        if ($next && $next->fresh()->market_status === Round::UPCOMING) {
            $this->market->open($next);
        }
    }
}
