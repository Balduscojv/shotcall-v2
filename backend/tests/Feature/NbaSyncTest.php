<?php

use App\Events\GameFinished;
use App\Integrations\Nba\DemoNbaProvider;
use App\Integrations\Nba\GameDataProvider;
use App\Integrations\Nba\NbaCdnClient;
use App\Models\FantasyTeam;
use App\Models\Game;
use App\Models\Player;
use App\Models\User;
use App\Services\Nba\GameSync;
use App\Services\Nba\ScheduleSync;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

// Respostas no formato dos JSONs de cdn.nba.com, montadas à mão (o CDN recusa este ambiente).
function cdnSchedule(): array
{
    return ['leagueSchedule' => ['gameDates' => [[
        'gameDate' => '11/10/2026 00:00:00',
        'games' => [
            [
                'gameId' => '0022600123', 'gameStatus' => 1, 'gameDateTimeUTC' => '2026-11-11T00:30:00Z',
                'arenaName' => 'TD Garden',
                'broadcasters' => ['nationalBroadcasters' => [['broadcasterDisplay' => 'ESPN']]],
                'homeTeam' => ['teamId' => 1610612738, 'teamTricode' => 'BOS', 'teamCity' => 'Boston', 'teamName' => 'Celtics'],
                'awayTeam' => ['teamId' => 1610612747, 'teamTricode' => 'LAL', 'teamCity' => 'Los Angeles', 'teamName' => 'Lakers'],
            ],
            [
                'gameId' => '0012600007', 'gameStatus' => 3, 'gameDateTimeUTC' => '2026-10-10T23:00:00Z',
                'arenaName' => '', 'broadcasters' => [],
                'homeTeam' => ['teamId' => 1610612752, 'teamTricode' => 'NYK', 'teamCity' => 'New York', 'teamName' => 'Knicks'],
                'awayTeam' => ['teamId' => 1610612765, 'teamTricode' => 'DET', 'teamCity' => 'Detroit', 'teamName' => 'Pistons'],
            ],
        ],
    ]]]];
}

function cdnBox(int $status = 3): array
{
    $player = fn (int $id, string $name, string $pos, array $s) => [
        'personId' => $id, 'name' => $name, 'position' => $pos, 'jerseyNum' => '0',
        'statistics' => $s + ['minutes' => 'PT36M12.00S', 'reboundsTotal' => 8, 'assists' => 5, 'plusMinusPoints' => 6.0],
    ];

    return ['game' => [
        'gameId' => '0022600123', 'gameStatus' => $status, 'period' => 4, 'gameClock' => 'PT00M00.00S',
        'homeTeam' => [
            'teamId' => 1610612738, 'score' => 110,
            'periods' => [['period' => 1, 'score' => 30], ['period' => 2, 'score' => 25], ['period' => 3, 'score' => 27], ['period' => 4, 'score' => 28]],
            'players' => [$player(1628369, 'Jayson Tatum', 'SF', ['points' => 31, 'fieldGoalsMade' => 11, 'fieldGoalsAttempted' => 22, 'threePointersMade' => 4])],
        ],
        'awayTeam' => [
            'teamId' => 1610612747, 'score' => 108,
            'periods' => [['period' => 1, 'score' => 28], ['period' => 2, 'score' => 27], ['period' => 3, 'score' => 26], ['period' => 4, 'score' => 27]],
            'players' => [$player(2544, 'LeBron James', '', ['points' => 28])],
        ],
    ]];
}

function cdnPlays(): array
{
    return ['game' => ['actions' => [
        ['actionNumber' => 4, 'period' => 1, 'clock' => 'PT11M40.00S', 'teamId' => 1610612747, 'personId' => 2544, 'actionType' => '2pt',
            'isFieldGoal' => 1, 'shotResult' => 'Made', 'shotValue' => 2, 'x' => 8.5, 'y' => 51.2, 'scoreHome' => '0', 'scoreAway' => '2', 'description' => 'James Layup'],
        ['actionNumber' => 7, 'period' => 1, 'clock' => 'PT11M20.00S', 'teamId' => 1610612738, 'personId' => 1628369, 'actionType' => 'rebound',
            'isFieldGoal' => 0, 'scoreHome' => '0', 'scoreAway' => '2'],
        ['actionNumber' => 699, 'period' => 4, 'clock' => 'PT00M03.40S', 'teamId' => 1610612738, 'personId' => 1628369, 'actionType' => '3pt',
            'isFieldGoal' => 1, 'shotResult' => 'Made', 'shotValue' => 3, 'x' => 70.1, 'y' => 12.0, 'scoreHome' => '110', 'scoreAway' => '108'],
        ['actionNumber' => 702, 'period' => 4, 'clock' => 'PT00M00.00S', 'actionType' => 'game', 'isFieldGoal' => 0, 'scoreHome' => '110', 'scoreAway' => '108'],
    ]]];
}

function fakeCdn(int $status = 3): void
{
    Http::fake([
        '*/scheduleLeagueV2.json' => Http::response(cdnSchedule()),
        '*/boxscore_0022600123.json' => Http::response(cdnBox($status)),
        '*/playbyplay_0022600123.json' => Http::response(cdnPlays()),
    ]);
    app()->instance(GameDataProvider::class, new NbaCdnClient);
}

it('normaliza o calendário do CDN', function () {
    fakeCdn();
    $games = app(GameDataProvider::class)->schedule();

    expect($games)->toHaveCount(2)
        ->and($games[0])->toMatchArray([
            'id' => '0022600123', 'phase' => 'REGULAR', 'status' => 'SCHEDULED', 'arena' => 'TD Garden', 'national_tv' => 'ESPN',
        ])
        ->and($games[1])->toMatchArray(['phase' => 'PRESEASON', 'status' => 'FINAL', 'arena' => null, 'national_tv' => null]);
});

it('normaliza as jogadas com o placar de cada uma', function () {
    fakeCdn();
    $plays = app(GameDataProvider::class)->playByPlay('0022600123', 4);

    expect(array_column($plays, 'action_number'))->toBe([7, 699, 702])
        ->and($plays[1])->toMatchArray([
            'clock' => '00:03', 'action_type' => '3pt', 'shot_result' => 'MADE', 'points' => 3,
            'score_home' => 110, 'score_away' => 108, 'player_id' => 1628369,
        ])
        ->and($plays[0]['shot_result'])->toBeNull()
        ->and($plays[2]['player_id'])->toBeNull();
});

it('salva o calendário e liga o jogo à rodada; pré-temporada fica sem rodada', function () {
    fakeCdn();
    expect(app(ScheduleSync::class)->run())->toBe(2);

    $game = Game::find('0022600123');
    expect($game->status)->toBe('SCHEDULED')
        ->and($game->round_id)->not->toBeNull()
        ->and($game->round->starts_at->lte($game->start_time))->toBeTrue()
        ->and($game->arena)->toBe('TD Garden')
        ->and(Game::find('0012600007')->round_id)->toBeNull();
});

it('rodar o calendário de novo não volta jogo encerrado para agendado', function () {
    fakeCdn();
    app(ScheduleSync::class)->run();
    Game::find('0022600123')->update(['status' => 'FINAL', 'home_score' => 110]);

    app(ScheduleSync::class)->run();

    expect(Game::find('0022600123'))->status->toBe('FINAL')->home_score->toBe(110);
});

it('atualiza o jogo: placar, quartos, stats, jogadas e dispara o fim uma vez só', function () {
    fakeCdn();
    app(ScheduleSync::class)->run();
    Event::fake([GameFinished::class]);
    $game = Game::find('0022600123');

    app(GameSync::class)->sync($game);

    expect($game->fresh())->status->toBe('FINAL')->home_score->toBe(110)->away_score->toBe(108)->last_action_number->toBe(702)
        ->and($game->periods()->count())->toBe(4)
        ->and($game->actions()->count())->toBe(4)
        ->and($game->stats()->where('player_id', 1628369)->value('pts'))->toBe(31)
        ->and(Player::find(1628369))->position->toBe('F')->full_name->toBe('Jayson Tatum')
        ->and(Player::find(2544)->position)->toBeNull();
    Event::assertDispatchedTimes(GameFinished::class, 1);

    app(GameSync::class)->sync($game->fresh());
    Event::assertDispatchedTimes(GameFinished::class, 1);
    expect($game->actions()->count())->toBe(4);
});

it('fim de jogo roda as missões: o Clutch sai para quem tinha o autor da cesta como cestinha', function () {
    fakeCdn();
    app(ScheduleSync::class)->run();
    $game = Game::find('0022600123');
    $ana = User::factory()->create(['handle' => 'ana']);
    Player::create(['id' => 1628369, 'team_id' => 1610612738, 'full_name' => 'Jayson Tatum', 'position' => 'F']);
    FantasyTeam::create(['user_id' => $ana->id, 'round_id' => $game->round_id, 'cestinha_id' => 1628369, 'balance' => 0, 'patrimony' => 100]);

    app(GameSync::class)->sync($game);

    expect($ana->cosmetics()->where('key', 'mission-clutch')->exists())->toBeTrue();
});

it('jogo ao vivo não dispara o fim', function () {
    fakeCdn(status: 2);
    app(ScheduleSync::class)->run();
    Event::fake([GameFinished::class]);

    app(GameSync::class)->sync(Game::find('0022600123'));

    expect(Game::find('0022600123')->status)->toBe('LIVE');
    Event::assertNotDispatched(GameFinished::class);
});

it('a demonstração simula a noite: passado encerrado, futuro agendado, ao vivo no meio', function () {
    app()->instance(GameDataProvider::class, $demo = new DemoNbaProvider);
    Carbon::setTestNow(Carbon::parse('2026-11-06 20:30', 'America/New_York'));

    app(ScheduleSync::class)->run();
    $tonight = Game::whereBetween('start_time', [Carbon::parse('2026-11-06 00:00', 'America/New_York')->utc(), Carbon::parse('2026-11-06 23:59', 'America/New_York')->utc()])
        ->orderBy('start_time')->get();
    expect($tonight)->toHaveCount(5);

    $sync = app(GameSync::class);
    $tonight->each(fn (Game $g) => $sync->sync($g));
    expect($tonight->map(fn ($g) => $g->fresh()->status)->all())->toBe(['LIVE', 'LIVE', 'LIVE', 'SCHEDULED', 'SCHEDULED']);

    $past = Game::where('start_time', '<', now()->subDay())->first();
    $sync->sync($past);
    $past->refresh();
    expect($past->status)->toBe('FINAL')
        ->and($past->home_score)->not->toBe($past->away_score)
        ->and($past->home_score + $past->away_score)->toBeGreaterThan(150)
        ->and((int) $past->periods()->sum('home_points'))->toBe($past->home_score);

    // Mesma consulta, mesmo resultado.
    expect($demo->boxScore($past->id)['home_score'])->toBe($past->home_score);
    Carbon::setTestNow();
});
