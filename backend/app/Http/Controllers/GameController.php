<?php

namespace App\Http\Controllers;

use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Models\PlayerGameStat;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Calendário e jogos. O dia é o da NBA (horário de Nova York): o jogo das 22h30 lá, que no
 * Brasil já é madrugada, continua na mesma noite. O front mostra os horários em Brasília.
 */
class GameController extends Controller
{
    private const TZ = 'America/New_York';

    /** GET /games?date=YYYY-MM-DD — jogos do dia, a semana em volta e o dia anterior/seguinte com jogo. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']], ['date.date_format' => 'Use a data no formato AAAA-MM-DD.']);
        $day = Carbon::parse($request->query('date', Carbon::now(self::TZ)->toDateString()), self::TZ)->startOfDay();
        [$from, $to] = [$day->copy()->utc(), $day->copy()->endOfDay()->utc()];

        $games = Game::with(['home', 'away'])->whereBetween('start_time', [$from, $to])->orderBy('start_time')->orderBy('id')->get();

        $weekStart = $day->copy()->startOfWeek(Carbon::MONDAY);
        $inWeek = Game::whereBetween('start_time', [$weekStart->copy()->utc(), $weekStart->copy()->addDays(6)->endOfDay()->utc()])
            ->get(['start_time', 'status'])
            ->groupBy(fn (Game $g) => $g->start_time->setTimezone(self::TZ)->toDateString());
        $week = collect(range(0, 6))->map(function (int $i) use ($weekStart, $inWeek) {
            $date = $weekStart->copy()->addDays($i)->toDateString();
            $games = $inWeek->get($date, collect());

            return ['date' => $date, 'games' => $games->count(), 'live' => $games->where('status', 'LIVE')->count()];
        });

        $prev = Game::where('start_time', '<', $from)->max('start_time');
        $next = Game::where('start_time', '>', $to)->min('start_time');

        return GameResource::collection($games)->additional(['meta' => [
            'date' => $day->toDateString(),
            'today' => Carbon::now(self::TZ)->toDateString(),
            'week' => $week,
            'prev_date' => $prev ? Carbon::parse($prev)->setTimezone(self::TZ)->toDateString() : null,
            'next_date' => $next ? Carbon::parse($next)->setTimezone(self::TZ)->toDateString() : null,
        ]]);
    }

    /** GET /games/{game} — placar, quartos, arremessos, líderes e box score por time. */
    public function show(Game $game): JsonResponse
    {
        $game->load(['home', 'away', 'periods' => fn ($q) => $q->orderBy('period')]);
        $stats = PlayerGameStat::with('player:id,full_name,position,jersey')->where('game_id', $game->id)->get();

        $shots = $game->actions()
            ->whereIn('action_type', ['2pt', '3pt'])->whereNotNull('x')->whereNotNull('y')
            ->orderBy('action_number')
            ->get(['action_number', 'period', 'clock', 'team_id', 'player_id', 'action_type', 'shot_result', 'x', 'y'])
            ->map(fn ($a) => [
                'n' => $a->action_number,
                'period' => $a->period,
                'clock' => $a->clock,
                'team_id' => $a->team_id,
                'player_id' => $a->player_id,
                'three' => $a->action_type === '3pt',
                'made' => $a->shot_result === 'MADE',
                'x' => (float) $a->x,
                'y' => (float) $a->y,
            ]);

        return response()->json(['data' => [
            ...(new GameResource($game))->resolve(),
            'periods' => $game->periods->map(fn ($p) => [
                'period' => $p->period, 'home_points' => $p->home_points, 'away_points' => $p->away_points,
            ]),
            'teams' => [
                'home' => $this->teamSheet($stats->where('team_id', $game->home_team_id)),
                'away' => $this->teamSheet($stats->where('team_id', $game->away_team_id)),
            ],
            'shots' => $shots,
        ]]);
    }

    /** Totais, líderes e box score de um time. */
    private function teamSheet(Collection $stats): array
    {
        $sum = fn (string $col) => (int) $stats->sum($col);
        $leader = function (string $col) use ($stats) {
            $top = $stats->sortByDesc($col)->first();

            return $top && $top->{$col} > 0
                ? ['player_id' => $top->player_id, 'name' => $top->player?->full_name, 'value' => (int) $top->{$col}]
                : null;
        };

        return [
            'totals' => [
                'pts' => $sum('pts'), 'reb' => $sum('reb'), 'ast' => $sum('ast'), 'stl' => $sum('stl'), 'blk' => $sum('blk'),
                'tov' => $sum('tov'), 'fgm' => $sum('fgm'), 'fga' => $sum('fga'), 'fg3m' => $sum('fg3m'),
                'ftm' => $sum('ftm'), 'fta' => $sum('fta'),
            ],
            'leaders' => ['pts' => $leader('pts'), 'reb' => $leader('reb'), 'ast' => $leader('ast')],
            // Quem jogou primeiro (mais minutos), depois os que não entraram.
            'players' => $stats
                ->sortBy([fn ($a, $b) => self::seconds($b->minutes) <=> self::seconds($a->minutes), fn ($a, $b) => $b->pts <=> $a->pts])
                ->values()
                ->map(fn (PlayerGameStat $s) => [
                    'player_id' => $s->player_id,
                    'name' => $s->player?->full_name,
                    'position' => $s->player?->position,
                    'jersey' => $s->player?->jersey,
                    'minutes' => $s->minutes,
                    'pts' => $s->pts, 'reb' => $s->reb, 'ast' => $s->ast, 'stl' => $s->stl, 'blk' => $s->blk, 'tov' => $s->tov,
                    'fgm' => $s->fgm, 'fga' => $s->fga, 'fg3m' => $s->fg3m, 'ftm' => $s->ftm, 'fta' => $s->fta,
                    'plus_minus' => $s->plus_minus,
                ]),
        ];
    }

    private static function seconds(?string $minutes): int
    {
        if (! $minutes || ! preg_match('/^(\d+):(\d+)/', $minutes, $m)) {
            return 0;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }
}
