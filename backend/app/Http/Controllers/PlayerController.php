<?php

namespace App\Http\Controllers;

use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Models\Player;
use App\Models\PlayerGameScore;
use App\Models\PlayerGameStat;
use App\Models\PlayerPrice;
use App\Models\Round;
use App\Services\Fantasy\FantasyMarket;
use Illuminate\Http\JsonResponse;

/** Página do jogador no fantasy: preço, histórico por rodada, jogos e próximos jogos. */
class PlayerController extends Controller
{
    public function __construct(private FantasyMarket $market) {}

    /** GET /players/{player} */
    public function show(Player $player): JsonResponse
    {
        $player->load('team');
        $round = $this->market->currentRound();
        $seasonId = $round?->season_id;

        // Preço em cada rodada da temporada, em ordem.
        $prices = PlayerPrice::query()
            ->join('rounds', 'rounds.id', '=', 'player_prices.round_id')
            ->where('player_prices.player_id', $player->id)
            ->when($seasonId, fn ($q) => $q->where('rounds.season_id', $seasonId))
            ->orderBy('rounds.number')
            ->get(['rounds.id as round_id', 'rounds.number', 'player_prices.price', 'player_prices.variation', 'player_prices.min_to_gain']);
        $current = $round ? $prices->firstWhere('round_id', $round->id) : null;

        // Jogos já pontuados na temporada, do mais recente para o mais antigo.
        $scores = PlayerGameScore::where('player_id', $player->id)->get()->keyBy('game_id');
        $stats = PlayerGameStat::where('player_id', $player->id)->get()->keyBy('game_id');
        $played = Game::with(['home', 'away'])
            ->whereIn('id', $scores->keys())
            ->when($seasonId, fn ($q) => $q->where('season_id', $seasonId))
            ->orderByDesc('start_time')
            ->get();

        $points = $played->map(fn (Game $g) => (float) $scores[$g->id]->points);

        // Próximos jogos do time (os que ainda não começaram).
        $upcoming = $player->team_id
            ? Game::with(['home', 'away'])
                ->where(fn ($q) => $q->where('home_team_id', $player->team_id)->orWhere('away_team_id', $player->team_id))
                ->where('status', 'SCHEDULED')
                ->where('start_time', '>=', now()->subHours(6))
                ->orderBy('start_time')
                ->limit(5)
                ->get()
            : collect();

        return response()->json(['data' => [
            'id' => (int) $player->id,
            'name' => $player->full_name,
            'position' => $player->position,
            'role' => $player->fantasyRole(),
            'jersey' => $player->jersey,
            'active' => (bool) $player->active,
            'team' => $player->team ? GameResource::team($player->team) : null,
            'round' => $round ? ['id' => $round->id, 'number' => $round->number, 'status' => $round->market_status] : null,
            'price' => $current ? (float) $current->price : null,
            'variation' => $current ? (float) $current->variation : null,
            'min_to_gain' => $current ? (float) $current->min_to_gain : null,
            'games_in_round' => $round && $player->team_id ? ($this->market->gamesPerTeam($round)[$player->team_id] ?? 0) : 0,
            'season' => [
                'games' => $played->count(),
                'average' => $played->isEmpty() ? null : round($points->avg(), 1),
                'best' => $played->isEmpty() ? null : $points->max(),
                'total' => round($points->sum(), 2),
            ],
            'price_history' => $prices->map(fn ($p) => [
                'round' => (int) $p->number,
                'price' => (float) $p->price,
                'variation' => (float) $p->variation,
            ])->values(),
            'games' => $played->map(function (Game $g) use ($player, $scores, $stats) {
                $home = (int) $g->home_team_id === (int) $player->team_id;
                $s = $stats[$g->id] ?? null;

                return [
                    'id' => $g->id,
                    'start_time' => $g->start_time->toIso8601String(),
                    'home' => $home,
                    'opponent' => GameResource::team($home ? $g->away : $g->home),
                    'team_score' => $home ? $g->home_score : $g->away_score,
                    'opponent_score' => $home ? $g->away_score : $g->home_score,
                    'status' => $g->status,
                    'points' => (float) $scores[$g->id]->points,
                    'breakdown' => $scores[$g->id]->breakdown,
                    'line' => $s ? [
                        'minutes' => $s->minutes,
                        'pts' => $s->pts, 'reb' => $s->reb, 'ast' => $s->ast, 'stl' => $s->stl, 'blk' => $s->blk, 'tov' => $s->tov,
                        'fgm' => $s->fgm, 'fga' => $s->fga, 'fg3m' => $s->fg3m, 'ftm' => $s->ftm, 'fta' => $s->fta,
                        'plus_minus' => $s->plus_minus,
                    ] : null,
                ];
            })->values(),
            'upcoming' => $upcoming->map(fn (Game $g) => [
                'id' => $g->id,
                'start_time' => $g->start_time->toIso8601String(),
                'home' => (int) $g->home_team_id === (int) $player->team_id,
                'opponent' => GameResource::team((int) $g->home_team_id === (int) $player->team_id ? $g->away : $g->home),
            ])->values(),
        ]]);
    }
}
