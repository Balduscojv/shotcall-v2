<?php

namespace App\Services\Leagues;

use App\Models\FantasyTeam;
use App\Models\Game;
use App\Models\GameShotCall;
use App\Models\Round;
use App\Services\Fantasy\FantasyTeamService;
use App\Services\Fantasy\ShotCallBoost;
use App\Services\ShotCalls\ShotCallService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Parciais da rodada em andamento, as mesmas da tela do time e do palpite do jogo, para todo
 * mundo de uma vez (plano: "Tempo real na liga").
 *
 * - fantasy: pontos dos 5 (jogos encerrados + ao vivo, cestinha em dobro) × (1 + boost dos
 *   palpites já apurados na rodada), como FantasyController::teamView.
 * - shot calls: pontos que os palpites ainda não apurados fariam "se o jogo acabasse agora",
 *   como GameController::shotCallBlock. Os já apurados vêm do banco, não daqui.
 *
 * Calcula uma vez por rodada e guarda por alguns segundos: toda liga filtra os seus membros do
 * mesmo resultado, inclusive a Global. No cache vai array puro: o Laravel não desserializa
 * objetos de classes que não estão liberadas (viraria __PHP_Incomplete_Class).
 */
class LivePartials
{
    private const TTL_SECONDS = 30;

    public function __construct(
        private FantasyTeamService $teams,
        private ShotCallBoost $boost,
        private ShotCallService $shotCalls,
    ) {}

    /** A rodada já começou e ainda não foi apurada: os pontos dela são parciais. */
    public static function inProgress(Round $round): bool
    {
        return in_array($round->market_status, [Round::LOCKED, Round::SETTLING], true);
    }

    /** Tem jogo da rodada rolando agora. */
    public function hasLiveGames(Round $round): bool
    {
        return Game::where('round_id', $round->id)->where('status', 'LIVE')->exists();
    }

    /** @return Collection<int, float> pontos parciais do time por user_id */
    public function fantasy(Round $round): Collection
    {
        return collect(Cache::remember("league-live:fantasy:{$round->id}", self::TTL_SECONDS, function () use ($round) {
            $teams = FantasyTeam::with('players')->where('round_id', $round->id)->get();
            $points = $this->teams->roundPoints($round, $teams->flatMap->players->pluck('player_id')->unique()->values());
            $boosts = $this->boost->forRound($round);

            return $teams->mapWithKeys(fn (FantasyTeam $team) => [
                $team->user_id => ShotCallBoost::apply($this->teams->teamPoints($team, $points), (float) ($boosts[$team->user_id] ?? 0)),
            ])->all();
        }));
    }

    /** @return Collection<int, int> pontos provisórios dos palpites ainda não apurados, por user_id */
    public function shotCalls(Round $round): Collection
    {
        return collect(Cache::remember("league-live:shotcall:{$round->id}", self::TTL_SECONDS, function () use ($round) {
            $totals = collect();
            // Ao vivo, ou encerrado com palpites que a fila ainda não apurou.
            $games = Game::where('round_id', $round->id)
                ->where(fn ($q) => $q->where('status', 'LIVE')
                    ->orWhere(fn ($f) => $f->where('status', 'FINAL')->whereHas('shotCalls', fn ($c) => $c->whereIn('status', ['OPEN', 'LOCKED']))))
                ->get();

            foreach ($games as $game) {
                $result = $this->shotCalls->result($game);
                GameShotCall::where('game_id', $game->id)->whereIn('status', ['OPEN', 'LOCKED'])->get()
                    ->each(function (GameShotCall $call) use ($result, $totals) {
                        $totals[$call->user_id] = ($totals[$call->user_id] ?? 0) + $this->shotCalls->check($call, $result)['points'];
                    });
            }

            return $totals->all();
        }));
    }
}
