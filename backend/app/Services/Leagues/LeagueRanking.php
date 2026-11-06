<?php

namespace App\Services\Leagues;

use App\Models\League;
use App\Models\LeagueStanding;
use App\Models\Round;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Rankings da liga (plano: "Rankings da liga").
 *
 * - fantasy: pontos do time somados nas rodadas do período; desempata pelo patrimônio.
 * - patrimony: patrimônio do time na rodada; não tem período.
 * - shotcall: pontos dos palpites de jogo do período; desempata pela taxa de acerto de jogo.
 *
 * Cada membro só soma a partir da rodada em que entrou (joined_round_number). O cálculo é em
 * PHP sobre linhas já agregadas no banco, para valer igual no Postgres e no SQLite dos testes.
 */
class LeagueRanking
{
    public const BOARDS = ['fantasy', 'patrimony', 'shotcall'];

    public const PERIODS = ['round', 'month', 'season'];

    private const MONTHS = [1 => 'janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    /** Rodada que o ranking mostra quando ninguém escolheu: a última com pontos de fantasy, ou a atual. */
    public function defaultRound(string $board): ?Round
    {
        $current = Round::current();
        if ($board !== 'fantasy' || ! $current) {
            return $current ?? Round::orderByDesc('starts_at')->first();
        }

        return Round::where('season_id', $current->season_id)->where('market_status', Round::SETTLED)
            ->where('number', '<', $current->number)->orderByDesc('number')->first() ?? $current;
    }

    /** Rodadas do período que termina em $anchor. */
    public function roundsOf(string $period, Round $anchor): Collection
    {
        $season = Round::where('season_id', $anchor->season_id)->where('number', '<=', $anchor->number)->orderBy('number')->get();

        return match ($period) {
            'round' => collect([$anchor]),
            'month' => $season->filter(fn (Round $r) => $this->monthOf($r) === $this->monthOf($anchor))->values(),
            default => $season,
        };
    }

    public function monthLabel(Round $round): string
    {
        return self::MONTHS[(int) substr($this->monthOf($round), 5, 2)];
    }

    /**
     * Classificação: cada linha com user_id, score, rank (empates dividem a posição) e move
     * (quantas posições subiu desde a rodada anterior do período; null quando não há com o que comparar).
     *
     * @return Collection<int, array{user_id: int, score: float|null, rank: int, move: int|null}>
     */
    public function board(League $league, string $board, string $period, Round $anchor): Collection
    {
        $now = $this->rank($league, $board, $period, $anchor);

        $previous = $this->previousRanks($league, $board, $period, $anchor);

        return $now->map(fn (array $row) => [
            ...$row,
            'move' => isset($previous[$row['user_id']]) ? $previous[$row['user_id']] - $row['rank'] : null,
        ]);
    }

    /**
     * Guarda a foto do ranking da temporada de cada liga depois da apuração da rodada. É dela
     * que sai a posição mostrada no perfil.
     */
    public function snapshot(Round $round): void
    {
        League::whereNull('finished_at')->each(function (League $league) use ($round) {
            $board = in_array('FANTASY', $league->competitions, true) ? 'fantasy' : 'shotcall';
            $ranks = $this->rank($league, $board, 'season', $round)->keyBy('user_id');
            $fantasy = $this->fantasyPoints($league, collect([$round]));
            $shotcall = $this->shotCallPoints($league, collect([$round]));
            $patrimony = $this->patrimony($league, $round);

            $rows = $ranks->map(fn (array $row, int $userId) => [
                'league_id' => $league->id, 'round_id' => $round->id, 'user_id' => $userId,
                'fantasy_points' => $fantasy[$userId] ?? 0, 'shotcall_points' => (int) ($shotcall[$userId] ?? 0),
                'patrimony' => $patrimony[$userId] ?? 0, 'rank' => $row['rank'],
            ])->values();

            foreach ($rows->chunk(500) as $chunk) {
                LeagueStanding::upsert($chunk->all(), ['league_id', 'round_id', 'user_id'], ['fantasy_points', 'shotcall_points', 'patrimony', 'rank']);
            }
        });
    }

    /** @return Collection<int, array{user_id: int, score: float|null, rank: int}> */
    private function rank(League $league, string $board, string $period, Round $anchor): Collection
    {
        $rounds = $this->roundsOf($period, $anchor);
        $members = DB::table('league_members')->where('league_id', $league->id)->pluck('user_id');

        [$scores, $tiebreak] = match ($board) {
            'patrimony' => [$this->patrimony($league, $anchor), collect()],
            'shotcall' => [$this->shotCallPoints($league, $rounds), $this->gameAccuracy($league)],
            default => [$this->fantasyPoints($league, $rounds), $this->patrimony($league, $anchor)],
        };
        // Patrimônio só existe para quem tem time; os outros ficam no fim, sem número.
        $empty = $board === 'patrimony' ? null : 0.0;

        $rows = $members->map(fn (int $id) => [
            'user_id' => $id,
            'score' => isset($scores[$id]) ? round((float) $scores[$id], 2) : $empty,
            'tiebreak' => (float) ($tiebreak[$id] ?? -1),
        ])->sort(fn (array $a, array $b) => [$b['score'] ?? -INF, $b['tiebreak'], $a['user_id']] <=> [$a['score'] ?? -INF, $a['tiebreak'], $b['user_id']])->values();

        $rank = 0;
        $last = null;

        return $rows->map(function (array $row, int $i) use (&$rank, &$last) {
            $key = [$row['score'], $row['tiebreak']];
            if ($key !== $last) {
                $rank = $i + 1;
                $last = $key;
            }

            return ['user_id' => $row['user_id'], 'score' => $row['score'], 'rank' => $rank];
        });
    }

    /**
     * Posição de cada um antes da última rodada do período. Rodada isolada compara com a anterior;
     * mês e temporada, com o mesmo período sem a última rodada.
     *
     * @return array<int, int>
     */
    private function previousRanks(League $league, string $board, string $period, Round $anchor): array
    {
        $previous = Round::where('season_id', $anchor->season_id)->where('number', $anchor->number - 1)->first();
        if (! $previous) {
            return [];
        }
        // O mês começou nesta rodada: não há mês anterior para comparar.
        if ($period === 'month' && $this->monthOf($previous) !== $this->monthOf($anchor)) {
            return [];
        }

        return $this->rank($league, $board, $period, $previous)->pluck('rank', 'user_id')->all();
    }

    /** @return Collection<int, float> pontos de fantasy por user_id */
    private function fantasyPoints(League $league, Collection $rounds): Collection
    {
        return DB::table('league_members as m')
            ->join('fantasy_teams as t', 't.user_id', '=', 'm.user_id')
            ->join('rounds as r', 'r.id', '=', 't.round_id')
            ->where('m.league_id', $league->id)
            ->whereIn('t.round_id', $rounds->pluck('id'))
            ->where(fn ($q) => $q->whereNull('m.joined_round_number')->orWhereColumn('r.number', '>=', 'm.joined_round_number'))
            ->groupBy('m.user_id')
            ->selectRaw('m.user_id, sum(coalesce(t.points, 0)) as score')
            ->pluck('score', 'user_id')
            ->map(fn ($v) => (float) $v);
    }

    /** @return Collection<int, int> pontos de palpites de jogo por user_id */
    private function shotCallPoints(League $league, Collection $rounds): Collection
    {
        return DB::table('league_members as m')
            ->join('game_shot_calls as c', 'c.user_id', '=', 'm.user_id')
            ->join('games as g', 'g.id', '=', 'c.game_id')
            ->join('rounds as r', 'r.id', '=', 'g.round_id')
            ->where('m.league_id', $league->id)
            ->whereIn('g.round_id', $rounds->pluck('id'))
            ->where(fn ($q) => $q->whereNull('m.joined_round_number')->orWhereColumn('r.number', '>=', 'm.joined_round_number'))
            ->groupBy('m.user_id')
            ->selectRaw('m.user_id, sum(coalesce(c.points, 0)) as score')
            ->pluck('score', 'user_id')
            ->map(fn ($v) => (int) $v);
    }

    /** @return Collection<int, float> patrimônio do time na rodada, por user_id */
    private function patrimony(League $league, Round $round): Collection
    {
        return DB::table('league_members as m')
            ->join('fantasy_teams as t', 't.user_id', '=', 'm.user_id')
            ->where('m.league_id', $league->id)
            ->where('t.round_id', $round->id)
            ->pluck('t.patrimony', 'm.user_id')
            ->map(fn ($v) => (float) $v);
    }

    /** @return Collection<int, float> taxa de acerto nos palpites de jogo, por user_id */
    private function gameAccuracy(League $league): Collection
    {
        return DB::table('league_members as m')
            ->join('user_accuracy as a', 'a.user_id', '=', 'm.user_id')
            ->where('m.league_id', $league->id)
            ->where('a.mode', 'GAME')
            ->whereNotNull('a.rate')
            ->pluck('a.rate', 'm.user_id')
            ->map(fn ($v) => (float) $v);
    }

    /** "2026-11": mês da rodada pelo começo dela, no horário da NBA (Nova York). */
    private function monthOf(Round $round): string
    {
        return Carbon::parse($round->starts_at)->timezone('America/New_York')->format('Y-m');
    }
}
