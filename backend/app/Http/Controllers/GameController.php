<?php

namespace App\Http\Controllers;

use App\Http\Resources\GameResource;
use App\Models\Game;
use App\Models\GameShotCall;
use App\Models\Player;
use App\Models\PlayerGameStat;
use App\Models\User;
use App\Models\UserAccuracy;
use App\Services\ShotCalls\GameShotCallSettler;
use App\Services\ShotCalls\ShotCallService;
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
    public function __construct(private ShotCallService $shotCalls) {}

    private const TZ = 'America/New_York';

    /** GET /games?date=YYYY-MM-DD — jogos do dia, a semana em volta e o dia anterior/seguinte com jogo. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['date' => ['nullable', 'date_format:Y-m-d']], ['date.date_format' => 'Use a data no formato AAAA-MM-DD.']);
        $day = Carbon::parse($request->query('date', Carbon::now(self::TZ)->toDateString()), self::TZ)->startOfDay();
        [$from, $to] = [$day->copy()->utc(), $day->copy()->endOfDay()->utc()];

        $games = Game::with(['home', 'away'])->whereBetween('start_time', [$from, $to])->orderBy('start_time')->orderBy('id')->get();
        $mine = GameShotCall::where('user_id', $request->user()->id)->whereIn('game_id', $games->pluck('id'))->get()->keyBy('game_id');
        $games->each(fn (Game $g) => $g->setAttribute('my_shot_call', isset($mine[$g->id])
            ? ['status' => $mine[$g->id]->status, 'correct' => $mine[$g->id]->correct, 'points' => $mine[$g->id]->points]
            : null));

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
    public function show(Request $request, Game $game): JsonResponse
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
            'shot_call' => $this->shotCallBlock($request->user(), $game),
        ]]);
    }

    /**
     * Meu palpite neste jogo: o que escolhi, e conforme o momento, os candidatos a MVP (antes do
     * jogo), a parcial (ao vivo) ou o resultado (encerrado). Mais a minha taxa no modo jogo.
     */
    private function shotCallBlock(User $user, Game $game): array
    {
        $call = GameShotCall::with(['mvp:id,full_name,team_id'])->where('user_id', $user->id)->where('game_id', $game->id)->first();
        $locked = $game->isLocked();
        $scored = in_array($game->status, ['LIVE', 'FINAL'], true);
        $result = $scored ? $this->shotCalls->result($game) : null;

        $mine = null;
        if ($call) {
            $check = $result ? $this->shotCalls->check($call, $result) : null;
            $mine = [
                'status' => $call->status,
                'winner_team_id' => (int) $call->winner_team_id,
                'margin_band' => $call->margin_band,
                'mvp' => $call->mvp ? ['id' => (int) $call->mvp->id, 'name' => $call->mvp->full_name, 'team_id' => (int) $call->mvp->team_id] : null,
                // Encerrado e apurado: o resultado salvo. Ao vivo: "se acabasse agora".
                'correct' => $call->status === 'SETTLED' ? (int) $call->correct : $check['correct'] ?? null,
                'points' => $call->status === 'SETTLED' ? (int) $call->points : $check['points'] ?? null,
                'markets' => $check['markets'] ?? null,
                'provisional' => $call->status !== 'SETTLED',
            ];
        }

        $mvpLeader = null;
        if ($result && $result['mvp_player_ids']) {
            $leader = Player::find($result['mvp_player_ids'][0]);
            $mvpLeader = $leader ? ['id' => (int) $leader->id, 'name' => $leader->full_name, 'team_id' => (int) $leader->team_id, 'points' => $result['mvp_points']] : null;
        }

        $acc = UserAccuracy::where('user_id', $user->id)->where('mode', 'GAME')->first();

        return [
            'locked' => $locked,
            'lock_at' => $game->start_time->toIso8601String(),
            'mine' => $mine,
            'candidates' => $locked ? [] : $this->shotCalls->candidates($game),
            'margin' => $scored ? GameShotCallSettler::marginBand($game->home_score - $game->away_score) : null,
            'mvp_leader' => $mvpLeader,
            'accuracy' => [
                'rate' => $acc?->rate === null ? null : (float) $acc->rate,
                'counted' => (int) ($acc->counted ?? 0),
                'tier' => $acc?->tier,
            ],
        ];
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
