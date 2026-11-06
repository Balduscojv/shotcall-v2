<?php

use App\Integrations\Nba\DemoNbaProvider;
use App\Integrations\Nba\GameDataProvider;
use App\Models\Game;
use App\Models\User;
use App\Services\Nba\GameSync;
use App\Services\Nba\ScheduleSync;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    Carbon::setTestNow(Carbon::parse('2026-11-06 20:30', 'America/New_York'));
    app()->instance(GameDataProvider::class, new DemoNbaProvider);
    app(ScheduleSync::class)->run();
    $this->actingAs(User::factory()->create());
});

afterEach(fn () => Carbon::setTestNow());

it('pede login', function () {
    auth()->logout();
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/games')->assertUnauthorized();
});

it('lista os jogos do dia da NBA, com a semana em volta', function () {
    $res = $this->getJson('/api/games')->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('meta.date', '2026-11-06')
        ->assertJsonPath('meta.today', '2026-11-06')
        ->assertJsonPath('meta.prev_date', '2026-11-05')
        ->assertJsonPath('meta.next_date', '2026-11-07');

    // Semana de segunda (02/11) a domingo (08/11).
    expect(collect($res->json('meta.week'))->pluck('date')->all())
        ->toBe(['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-07', '2026-11-08'])
        ->and(collect($res->json('meta.week'))->pluck('games')->unique()->all())->toBe([5]);

    expect($res->json('data.0'))->toHaveKeys(['id', 'start_time', 'status', 'home.tricode', 'home.primary_color', 'away.tricode'])
        ->and($res->json('data.0.clock'))->toBeNull();
});

it('jogo das 22h30 em Nova York fica na mesma noite, mesmo já sendo madrugada no Brasil', function () {
    $late = $this->getJson('/api/games?date=2026-11-06')->json('data.4');

    expect(Carbon::parse($late['start_time'])->setTimezone('America/Sao_Paulo')->format('Y-m-d H:i'))->toBe('2026-11-07 00:30');
});

it('valida a data', function () {
    $this->getJson('/api/games?date=06/11/2026')->assertUnprocessable()->assertJsonValidationErrors('date');
});

it('dia sem jogo devolve lista vazia e aponta os vizinhos', function () {
    $this->getJson('/api/games?date=2026-12-25')->assertOk()
        ->assertJsonCount(0, 'data')
        ->assertJsonPath('meta.prev_date', '2026-11-13')
        ->assertJsonPath('meta.next_date', null);
});

it('detalhe do jogo: quartos, arremessos, líderes e box score', function () {
    $game = Game::where('start_time', '<', now()->subDay())->first();
    app(GameSync::class)->sync($game);
    $game->refresh();

    $res = $this->getJson("/api/games/{$game->id}")->assertOk()
        ->assertJsonPath('data.status', 'FINAL')
        ->assertJsonPath('data.home_score', $game->home_score)
        ->assertJsonCount(4, 'data.periods')
        ->assertJsonCount(8, 'data.teams.home.players');

    $home = $res->json('data.teams.home');
    expect($home['totals']['pts'])->toBe($game->home_score)
        ->and($home['leaders']['pts']['value'])->toBe(max(array_column($home['players'], 'pts')))
        ->and($res->json('data.shots'))->not->toBeEmpty()
        ->and($res->json('data.shots.0'))->toHaveKeys(['period', 'team_id', 'made', 'three', 'x', 'y']);
});

it('jogo que não existe dá 404', function () {
    $this->getJson('/api/games/0029999999')->assertNotFound();
});
