<?php

namespace App\Services\Fantasy;

use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerGameScore;
use App\Models\PlayerPrice;
use App\Models\Round;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mercado do fantasy: preço de cada jogador por rodada, abertura e trava.
 * Preço inicial = metade da média de pontos fantasy na temporada (entre 2 e 25); quem ainda
 * não jogou entra com o preço de novato. Depois o preço muda na apuração de cada rodada.
 */
class FantasyMarket
{
    public function __construct(private PriceVariation $pricing) {}

    /**
     * A rodada que o jogador vê: a de mercado aberto; senão a mais recente em andamento (a
     * anterior pode ainda estar esperando apuração); senão a próxima.
     */
    public function currentRound(): ?Round
    {
        return Round::where('market_status', Round::OPEN)->orderBy('starts_at')->first()
            ?? Round::whereIn('market_status', [Round::LOCKED, Round::SETTLING])->orderByDesc('starts_at')->first()
            ?? Round::where('market_status', Round::UPCOMING)->orderBy('starts_at')->first();
    }

    /** O mercado fecha alguns minutos antes do primeiro jogo da rodada (fantasy.lock_minutes_before). */
    public function lockAt(Round $round): ?Carbon
    {
        $first = Game::where('round_id', $round->id)->whereNot('status', 'POSTPONED')->min('start_time');

        return $first ? Carbon::parse($first)->subMinutes((int) config('fantasy.lock_minutes_before')) : null;
    }

    /**
     * Dá para mexer no time? Além do status, confere o horário: o agendador pode atrasar (no Render
     * Free ele dorme junto com a API) e o mercado não pode ficar aberto depois do fechamento.
     */
    public function isOpen(Round $round): bool
    {
        $lockAt = $this->lockAt($round);

        return $round->isMarketOpen() && ($lockAt === null || $lockAt->isFuture());
    }

    /**
     * Mercado aberto antes da apuração da rodada anterior: os preços são os da rodada anterior e
     * mudam quando ela for apurada (a valorização entra e os times salvos são reprecificados).
     */
    public function hasProvisionalPrices(Round $round): bool
    {
        $previous = $round->previous();

        return $round->isMarketOpen() && $previous !== null && $previous->market_status !== Round::SETTLED;
    }

    /** Abre o mercado da rodada: garante preço para todo jogador ativo e marca como aberta. */
    public function open(Round $round): int
    {
        $priced = $this->fillMissingPrices($round);
        $round->update(['market_status' => Round::OPEN]);

        return $priced;
    }

    /**
     * Fecha os mercados que chegaram no horário de fechamento e já abre o da rodada seguinte, para
     * ninguém ficar sem poder escalar enquanto a rodada rola.
     */
    public function lockStarted(): int
    {
        return Round::where('market_status', Round::OPEN)->get()
            ->filter(fn (Round $r) => ($at = $this->lockAt($r)) && $at->lte(now()))
            ->each(function (Round $r) {
                $r->update(['market_status' => Round::LOCKED]);
                $this->openNextEarly($r);
            })
            ->count();
    }

    /**
     * Abre o mercado da rodada seguinte com os preços da rodada que acabou de fechar, como
     * provisórios. A apuração (RoundSettlement) grava os preços novos por cima e reprecifica os
     * times já salvos.
     */
    public function openNextEarly(Round $round): void
    {
        $next = $round->next();
        if ($next === null || $next->market_status !== Round::UPCOMING) {
            return;
        }

        $rows = PlayerPrice::where('round_id', $round->id)
            ->whereNotIn('player_id', PlayerPrice::where('round_id', $next->id)->select('player_id'))
            ->get(['player_id', 'price', 'min_to_gain'])
            ->map(fn (PlayerPrice $p) => [
                'player_id' => $p->player_id, 'round_id' => $next->id, 'price' => $p->price,
                'variation' => 0, 'min_to_gain' => $p->min_to_gain,
            ]);

        DB::transaction(fn () => $rows->chunk(200)->each(fn ($chunk) => PlayerPrice::insert($chunk->values()->all())));
        $this->open($next);
    }

    /**
     * Preço inicial para quem ainda não tem preço na rodada (jogador novo, troca de elenco...).
     *
     * @return int quantos preços foram criados
     */
    public function fillMissingPrices(Round $round): int
    {
        $missing = Player::where('active', true)->whereNotNull('position')->whereNotNull('team_id')
            ->whereNotIn('id', PlayerPrice::where('round_id', $round->id)->select('player_id'))
            ->pluck('id');
        if ($missing->isEmpty()) {
            return 0;
        }

        $averages = $this->seasonAverages($round, $missing);
        $rows = $missing->map(function (int $id) use ($averages, $round) {
            $price = $this->pricing->initial($averages[$id]['average'] ?? null);

            return [
                'player_id' => $id, 'round_id' => $round->id, 'price' => $price,
                'variation' => 0, 'min_to_gain' => $this->pricing->minToGain($price),
            ];
        })->all();

        DB::transaction(fn () => collect($rows)->chunk(200)->each(fn ($chunk) => PlayerPrice::insert($chunk->values()->all())));

        return count($rows);
    }

    /**
     * Média de pontos fantasy por jogo na temporada (jogos já apurados até antes da rodada).
     *
     * @return Collection<int, array{average: float, games: int}>
     */
    public function seasonAverages(Round $round, Collection $playerIds): Collection
    {
        return PlayerGameScore::query()
            ->join('games', 'games.id', '=', 'player_game_scores.game_id')
            ->where('games.season_id', $round->season_id)
            ->where('games.start_time', '<', $round->starts_at)
            ->whereIn('player_id', $playerIds)
            ->groupBy('player_id')
            ->selectRaw('player_id, avg(points) as average, count(*) as games')
            ->get()
            ->mapWithKeys(fn ($r) => [(int) $r->player_id => ['average' => round((float) $r->average, 1), 'games' => (int) $r->games]]);
    }

    /**
     * Quantos jogos cada time tem na rodada (mais jogos, mais chance de pontuar).
     *
     * @return Collection<int, int> team_id => jogos
     */
    public function gamesPerTeam(Round $round): Collection
    {
        $games = Game::where('round_id', $round->id)->whereNot('status', 'POSTPONED')->get(['home_team_id', 'away_team_id']);

        return $games->pluck('home_team_id')->concat($games->pluck('away_team_id'))->countBy()->mapWithKeys(fn ($n, $id) => [(int) $id => $n]);
    }
}
