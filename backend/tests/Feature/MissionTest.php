<?php

use App\Models\FantasyTeam;
use App\Models\Game;
use App\Models\GameAction;
use App\Models\Player;
use App\Models\Round;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Services\Missions\MissionRunner;
use Database\Seeders\DatabaseSeeder;

// Jogo inventado: BOS (casa) x LAL. O Tatum é o cestinha da Ana nesta rodada.
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');

    $season = Season::where('label', '2026-27')->firstOrFail();
    $this->round = Round::where('season_id', $season->id)->where('number', 1)->firstOrFail();

    $this->bos = Team::where('tricode', 'BOS')->firstOrFail();
    $this->lal = Team::where('tricode', 'LAL')->firstOrFail();
    $this->tatum = Player::create(['id' => 10, 'team_id' => $this->bos->id, 'full_name' => 'Jayson Tatum', 'position' => 'F']);
    $this->lebron = Player::create(['id' => 20, 'team_id' => $this->lal->id, 'full_name' => 'LeBron James', 'position' => 'F']);

    $this->game = Game::create([
        'id' => '0022600001', 'season_id' => $season->id, 'round_id' => $this->round->id, 'phase' => 'REGULAR',
        'home_team_id' => $this->bos->id, 'away_team_id' => $this->lal->id, 'start_time' => now(), 'status' => 'FINAL',
        'home_score' => 110, 'away_score' => 108,
    ]);

    $this->ana = User::factory()->create(['handle' => 'ana']);
    $this->bia = User::factory()->create(['handle' => 'bia']);
    FantasyTeam::create(['user_id' => $this->ana->id, 'round_id' => $this->round->id, 'cestinha_id' => 10, 'balance' => 0, 'patrimony' => 100]);
    FantasyTeam::create(['user_id' => $this->bia->id, 'round_id' => $this->round->id, 'cestinha_id' => 20, 'balance' => 0, 'patrimony' => 100]);
});

/** Jogada do jogo-a-jogo, com o placar logo depois dela. */
function play(int $n, int $period, string $clock, int $playerId, string $type, int $home, int $away, string $result = 'MADE'): void
{
    GameAction::create([
        'game_id' => '0022600001', 'action_number' => $n, 'period' => $period, 'clock' => $clock,
        'team_id' => $playerId === 10 ? test()->bos->id : test()->lal->id, 'player_id' => $playerId, 'action_type' => $type,
        'shot_result' => $result, 'points' => $result === 'MADE' ? ($type === '3pt' ? 3 : ($type === '2pt' ? 2 : 1)) : 0,
        'score_home' => $home, 'score_away' => $away,
    ]);
}

function clutchOwners(): array
{
    return User::whereHas('cosmetics', fn ($q) => $q->where('key', 'mission-clutch'))->pluck('handle')->all();
}

it('libera o Clutch para quem tinha o autor da cesta decisiva como cestinha', function () {
    play(500, 4, '01:30', 20, '2pt', 105, 108);
    play(510, 4, '00:04', 10, '3pt', 110, 108);   // virada no fim: última cesta de quadra

    expect(app(MissionRunner::class)->gameFinished($this->game))->toBe(1)
        ->and(clutchOwners())->toBe(['ana']);

    $award = $this->ana->cosmetics()->where('key', 'mission-clutch')->first();
    expect($award->pivot->unlock_reason)->toBe('Missão Clutch · LAL @ BOS');

    // Rodar de novo não repete.
    expect(app(MissionRunner::class)->gameFinished($this->game))->toBe(0);
});

it('lance livre depois não tira a cesta decisiva, e não conta como última cesta', function () {
    play(500, 4, '00:20', 10, '2pt', 108, 106);
    play(510, 4, '00:05', 20, 'freethrow', 108, 107);
    play(520, 4, '00:02', 10, 'freethrow', 110, 108);

    app(MissionRunner::class)->gameFinished($this->game);

    expect(clutchOwners())->toBe(['ana']);
});

it('não libera em cesta de time que perdeu', function () {
    $this->game->update(['home_score' => 108, 'away_score' => 110]);
    play(500, 4, '00:30', 20, '2pt', 106, 110);
    play(510, 4, '00:03', 10, '2pt', 108, 110);   // última cesta, mas BOS perdeu

    app(MissionRunner::class)->gameFinished($this->game);

    expect(clutchOwners())->toBe([]);
});

it('não libera em lavada nem fora dos últimos 2 minutos', function (int $period, string $clock, int $home, int $away) {
    $this->game->update(['home_score' => $home, 'away_score' => $away]);
    play(510, $period, $clock, 10, '3pt', $home, $away);

    app(MissionRunner::class)->gameFinished($this->game);

    expect(clutchOwners())->toBe([]);
})->with([
    'lavada no fim' => [4, '00:10', 130, 100],
    'cedo demais' => [4, '02:30', 110, 108],
    'terceiro quarto' => [3, '00:01', 110, 108],
]);

it('vale na prorrogação', function () {
    play(600, 5, '00:40', 10, '2pt', 110, 108);

    app(MissionRunner::class)->gameFinished($this->game);

    expect(clutchOwners())->toBe(['ana']);
});

it('não roda em jogo que não terminou', function () {
    $this->game->update(['status' => 'LIVE']);
    play(510, 4, '00:04', 10, '3pt', 110, 108);

    expect(app(MissionRunner::class)->gameFinished($this->game))->toBe(0)
        ->and(clutchOwners())->toBe([]);
});

it('o comando roda as missões de um jogo encerrado', function () {
    play(510, 4, '00:04', 10, '3pt', 110, 108);

    $this->artisan('missions:run', ['game' => '0022600001'])->expectsOutputToContain('1 prêmio(s) liberado(s)')->assertSuccessful();
    $this->artisan('missions:run', ['game' => 'nao-existe'])->assertFailed();
});

it('missão bloqueada aparece como ??? com a dica, e se revela depois de liberada', function () {
    $this->actingAs($this->ana);

    $res = $this->getJson('/api/me/cosmetics')->assertOk();
    expect(collect($res->json('data'))->pluck('key'))->not->toContain('mission-clutch')
        ->and($res->json('meta.locked'))->toHaveCount(1)
        ->and($res->json('meta.locked.0'))->toMatchArray([
            'slot' => 'TITLE', 'rarity' => 'RARE', 'hint' => 'Confie no seu cestinha quando o relógio apertar.',
        ])
        ->and($res->json('meta.locked.0'))->not->toHaveKeys(['key', 'name', 'description']);

    play(510, 4, '00:04', 10, '3pt', 110, 108);
    app(MissionRunner::class)->gameFinished($this->game);

    $res = $this->getJson('/api/me/cosmetics')->assertOk();
    expect($res->json('meta.locked'))->toBe([])
        ->and(collect($res->json('data'))->firstWhere('key', 'mission-clutch'))
        ->toMatchArray(['name' => 'Clutch', 'secret' => true, 'owned' => true]);

    $this->putJson('/api/me/cosmetics/title', ['key' => 'mission-clutch'])->assertOk()
        ->assertJsonPath('data.equipped.title.name', 'Clutch');
});
