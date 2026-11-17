<?php

namespace App\Http\Controllers;

use App\Http\Resources\GameResource;
use App\Models\FantasyTeam;
use App\Models\Player;
use App\Models\PlayerPrice;
use App\Models\Round;
use App\Models\Team;
use App\Services\Fantasy\FantasyMarket;
use App\Services\Fantasy\FantasyTeamService;
use App\Services\Fantasy\ShotCallBoost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Fantasy: mercado da rodada e meu time. */
class FantasyController extends Controller
{
    public function __construct(private FantasyMarket $market, private FantasyTeamService $teams, private ShotCallBoost $boost) {}

    /** GET /fantasy/market — todos os jogadores com preço na rodada (o filtro é no front). */
    public function market(): JsonResponse
    {
        $round = $this->market->currentRound() ?? abort(404, 'Nenhuma rodada no calendário.');
        $prices = PlayerPrice::where('round_id', $round->id)->get()->keyBy('player_id');
        $players = Player::whereIn('id', $prices->keys())->where('active', true)->get(['id', 'full_name', 'team_id', 'position', 'role', 'jersey']);
        $averages = $this->market->seasonAverages($round, $players->pluck('id'));
        $games = $this->market->gamesPerTeam($round);

        return response()->json([
            'data' => $players->map(fn (Player $p) => [
                'id' => (int) $p->id,
                'name' => $p->full_name,
                'team_id' => (int) $p->team_id,
                'position' => $p->position,
                'role' => $p->fantasyRole(),
                'jersey' => $p->jersey,
                'price' => (float) $prices[$p->id]->price,
                'variation' => (float) $prices[$p->id]->variation,
                'min_to_gain' => (float) $prices[$p->id]->min_to_gain,
                'average' => $averages[$p->id]['average'] ?? null,
                'games_played' => $averages[$p->id]['games'] ?? 0,
                'games_in_round' => $games[$p->team_id] ?? 0,
            ])->sortByDesc('price')->values(),
            'meta' => [
                'round' => $this->roundInfo($round),
                'teams' => Team::orderBy('tricode')->get()->map(fn (Team $t) => GameResource::team($t)),
            ],
        ]);
    }

    /** GET /fantasy/team — meu time na rodada atual, com parciais. */
    public function team(Request $request): JsonResponse
    {
        $round = $this->market->currentRound() ?? abort(404, 'Nenhuma rodada no calendário.');
        $team = $this->teams->teamFor($request->user(), $round);

        // Mercado aberto antes da hora: a rodada anterior ainda está rolando; vão as parciais dela.
        $previous = null;
        if ($this->market->hasProvisionalPrices($round)) {
            $prevRound = $round->previous();
            $prevTeam = $this->teams->teamFor($request->user(), $prevRound);
            $previous = ['round' => $this->roundInfo($prevRound), 'team' => $prevTeam ? $this->teamView($prevTeam, $prevRound) : null];
        }

        return response()->json(['data' => [
            'round' => $this->roundInfo($round),
            'starting_balance' => (float) config('fantasy.starting_balance'),
            // Sem time salvo: o orçamento com que dá para escalar (provisório, se for o caso).
            'patrimony' => $this->teams->patrimonyFor($request->user(), $round),
            'cestinha_multiplier' => (float) config('fantasy.cestinha_multiplier'),
            'team' => $team ? $this->teamView($team, $round) : null,
            'previous' => $previous,
        ]]);
    }

    /** PUT /fantasy/team { players: [5 ids], cestinha_id } */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'players' => ['required', 'array', 'size:5'],
            'players.*' => ['integer', 'distinct'],
            'cestinha_id' => ['required', 'integer'],
        ], [
            'players.required' => 'Escale 5 jogadores.',
            'players.size' => 'Escale exatamente 5 jogadores.',
            'players.*.distinct' => 'Tem jogador repetido na escalação.',
            'cestinha_id.required' => 'Escolha o cestinha.',
        ]);

        $round = $this->market->currentRound() ?? abort(404, 'Nenhuma rodada no calendário.');
        $team = $this->teams->save($request->user(), $round, array_map('intval', $data['players']), (int) $data['cestinha_id']);

        return response()->json(['data' => $this->teamView($team, $round)]);
    }

    private function roundInfo(Round $round): array
    {
        return [
            'id' => $round->id,
            'number' => $round->number,
            'status' => $round->market_status,
            'starts_at' => $round->starts_at->toIso8601String(),
            'ends_at' => $round->ends_at->toIso8601String(),
            'lock_at' => $this->market->lockAt($round)?->toIso8601String(),
            'provisional_prices' => $this->market->hasProvisionalPrices($round),
        ];
    }

    private function teamView(FantasyTeam $team, Round $round): array
    {
        $ids = $team->players->pluck('player_id');
        $players = Player::with('team')->whereIn('id', $ids)->get()->keyBy('id');
        $prices = PlayerPrice::where('round_id', $round->id)->whereIn('player_id', $ids)->get()->keyBy('player_id');
        $points = $this->teams->roundPoints($round, $ids);
        $games = $this->market->gamesPerTeam($round);
        $mult = (float) config('fantasy.cestinha_multiplier');
        $cost = round($team->players->sum('bought_at_price'), 2);

        // Rodada apurada: o que foi salvo. Senão, a parcial (jogos encerrados + ao vivo) com o
        // boost dos palpites já apurados na rodada.
        $settled = $round->market_status === Round::SETTLED;
        $boost = $this->boost->forUser($team->user_id, $round);
        $base = $settled ? (float) $team->base_points : $this->teams->teamPoints($team, $points);
        $boostValue = $settled ? (float) $team->boost : $boost['boost'];

        return [
            'cestinha_id' => $team->cestinha_id ? (int) $team->cestinha_id : null,
            'balance' => (float) $team->balance,
            'patrimony' => (float) $team->patrimony,
            'cost' => $cost,
            'base_points' => $base,
            'points' => $settled ? (float) $team->points : ShotCallBoost::apply($base, $boostValue),
            'boost' => ['value' => $boostValue] + $boost,
            'players' => $team->players->sortBy(fn ($p) => array_search($p->slot, FantasyTeamService::SLOTS, true))->values()->map(function ($slot) use ($players, $prices, $points, $games, $team, $mult) {
                $p = $players[$slot->player_id];
                $isCestinha = (int) $slot->player_id === (int) $team->cestinha_id;
                $pts = $points[$slot->player_id] ?? ['points' => 0, 'games' => 0, 'live' => false];

                return [
                    'slot' => $slot->slot,
                    'id' => (int) $p->id,
                    'name' => $p->full_name,
                    'position' => $p->position,
                    'role' => $p->fantasyRole(),
                    'team' => $p->team ? GameResource::team($p->team) : null,
                    'price' => isset($prices[$p->id]) ? (float) $prices[$p->id]->price : (float) $slot->bought_at_price,
                    'bought_at_price' => (float) $slot->bought_at_price,
                    'points' => round($pts['points'] * ($isCestinha ? $mult : 1), 2),
                    'games_played' => $pts['games'],
                    'games_in_round' => $games[$p->team_id] ?? 0,
                    'live' => $pts['live'],
                    'cestinha' => $isCestinha,
                ];
            }),
        ];
    }
}
