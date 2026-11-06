<?php

namespace App\Services\Fantasy;

use App\Models\FantasyTeam;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerPrice;
use App\Models\PlayerRoundScore;
use App\Models\Round;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Meu time numa rodada: escalar (2 armadores, 2 alas, 1 pivô e o cestinha, que pontua em
 * dobro) dentro do patrimônio, e ver pontos e parciais.
 */
class FantasyTeamService
{
    /** Vagas na quadra: uma por posição, nesta ordem. */
    public const SLOTS = ['PG', 'SG', 'SF', 'PF', 'C'];

    public function __construct(
        private LineupValidator $validator,
        private GameScoring $scoring,
    ) {}

    public function teamFor(User $user, Round $round): ?FantasyTeam
    {
        return FantasyTeam::with('players')->where('user_id', $user->id)->where('round_id', $round->id)->first();
    }

    /** Patrimônio para escalar nesta rodada: o do time (se já tem) ou o inicial. */
    public function patrimonyFor(User $user, Round $round): float
    {
        $team = $this->teamFor($user, $round);

        return $team ? (float) $team->patrimony : (float) config('fantasy.starting_balance');
    }

    /**
     * Salva a escalação. Preço de compra = preço da rodada; saldo = patrimônio − custo.
     *
     * @param  list<int>  $playerIds
     */
    public function save(User $user, Round $round, array $playerIds, ?int $cestinhaId): FantasyTeam
    {
        return DB::transaction(function () use ($user, $round, $playerIds, $cestinhaId) {
            $round = Round::lockForUpdate()->findOrFail($round->id);
            $prices = PlayerPrice::where('round_id', $round->id)->whereIn('player_id', $playerIds)->pluck('price', 'player_id');
            $players = Player::whereIn('id', $playerIds)->get(['id', 'position', 'role'])->keyBy('id');

            $unknown = array_values(array_filter($playerIds, fn ($id) => ! isset($prices[$id], $players[$id])));
            if ($unknown) {
                throw ValidationException::withMessages(['players' => 'Algum jogador escolhido não está no mercado desta rodada.']);
            }

            // A escalação exige uma de cada posição detalhada (config fantasy.lineup).
            $lineup = array_map(fn ($id) => ['id' => $id, 'position' => $players[$id]->fantasyRole(), 'price' => (float) $prices[$id]], $playerIds);
            $patrimony = $this->patrimonyFor($user, $round);
            $errors = $this->validator->validate($lineup, $cestinhaId, $patrimony, $round->isMarketOpen());
            if ($errors) {
                throw ValidationException::withMessages(['players' => array_map(
                    fn ($code) => $this->message($code, $lineup, $patrimony),
                    $errors,
                )]);
            }

            $cost = round(array_sum(array_column($lineup, 'price')), 2);
            $team = FantasyTeam::updateOrCreate(
                ['user_id' => $user->id, 'round_id' => $round->id],
                ['cestinha_id' => $cestinhaId, 'patrimony' => $patrimony, 'balance' => round($patrimony - $cost, 2)],
            );

            $team->players()->delete();
            $team->players()->insert($this->slotted($lineup, $team->id));

            return $team->load('players');
        });
    }

    /**
     * Uma vaga por posição, na ordem da quadra: PG, SG, SF, PF, C.
     *
     * @return list<array{fantasy_team_id: int, slot: string, player_id: int, bought_at_price: float}>
     */
    public function slotted(array $lineup, int $teamId): array
    {
        $byPosition = collect($lineup)->keyBy('position');
        $rows = [];
        foreach (self::SLOTS as $slot) {
            if ($p = $byPosition[$slot] ?? null) {
                $rows[] = ['fantasy_team_id' => $teamId, 'slot' => $slot, 'player_id' => $p['id'], 'bought_at_price' => $p['price']];
            }
        }

        return $rows;
    }

    /**
     * Pontos de cada jogador na rodada até agora: jogos encerrados (acumulado da rodada) mais
     * os que estão ao vivo (pontuação parcial, sem salvar).
     *
     * @return Collection<int, array{points: float, games: int, live: bool}>
     */
    public function roundPoints(Round $round, Collection $playerIds): Collection
    {
        $done = PlayerRoundScore::where('round_id', $round->id)->whereIn('player_id', $playerIds)->get()->keyBy('player_id');
        $live = collect();
        foreach (Game::where('round_id', $round->id)->where('status', 'LIVE')->get() as $game) {
            $live = $live->union($this->scoring->current($game)->only($playerIds->all()));
        }

        return $playerIds->mapWithKeys(fn (int $id) => [$id => [
            'points' => round((float) ($done[$id]->points ?? 0) + ($live[$id] ?? 0), 2),
            'games' => (int) ($done[$id]->games ?? 0) + (isset($live[$id]) ? 1 : 0),
            'live' => isset($live[$id]),
        ]]);
    }

    /** Pontos do time: soma dos 5, com o cestinha multiplicado. */
    public function teamPoints(FantasyTeam $team, Collection $points): float
    {
        $mult = (float) config('fantasy.cestinha_multiplier');

        return round($team->players->sum(fn ($p) => ($points[$p->player_id]['points'] ?? 0) * ((int) $p->player_id === (int) $team->cestinha_id ? $mult : 1)), 2);
    }

    private function message(string $code, array $lineup, float $patrimony): string
    {
        $money = fn (float $v) => 'F$ '.number_format($v, 2, ',', '.');

        return match (true) {
            $code === 'market_closed' => 'O mercado está fechado: a rodada já começou.',
            $code === 'duplicate_player' => 'Tem jogador repetido na escalação.',
            $code === 'cestinha_not_in_lineup' => 'Escolha o cestinha entre os 5 escalados.',
            $code === 'insufficient_balance' => 'Patrimônio insuficiente: o time custa '.$money(array_sum(array_column($lineup, 'price'))).' e você tem '.$money($patrimony).'.',
            str_starts_with($code, 'position_') => match (explode('_', $code)[1]) {
                'PG' => 'Escale 1 armador (PG).',
                'SG' => 'Escale 1 ala-armador (SG).',
                'SF' => 'Escale 1 ala (SF).',
                'PF' => 'Escale 1 ala-pivô (PF).',
                default => 'Escale 1 pivô (C).',
            },
            default => 'Escalação inválida.',
        };
    }
}
