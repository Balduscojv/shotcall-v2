<?php

use App\Events\GameFinished;
use App\Models\Game;
use App\Models\GameShotCall;
use App\Models\Player;
use App\Models\PlayerGameScore;
use App\Models\PlayerGameStat;
use App\Models\Season;
use App\Models\Team;
use App\Models\User;
use App\Models\UserAccuracy;
use App\Services\ShotCalls\ShotCallService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Carbon;

// BOS (casa) x LAL às 20h de Nova York. Tatum e Brown no Boston, LeBron no Lakers.
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    Carbon::setTestNow(Carbon::parse('2026-11-10 18:00', 'America/New_York'));

    $this->bos = Team::where('tricode', 'BOS')->first();
    $this->lal = Team::where('tricode', 'LAL')->first();
    $this->mia = Team::where('tricode', 'MIA')->first();
    foreach ([[1, 'Jayson Tatum', $this->bos], [2, 'Jaylen Brown', $this->bos], [3, 'LeBron James', $this->lal], [4, 'Bam Adebayo', $this->mia]] as [$id, $name, $team]) {
        Player::create(['id' => $id, 'team_id' => $team->id, 'full_name' => $name, 'position' => 'F', 'active' => true]);
    }

    $this->game = shotCallGame('401000001', '2026-11-10 20:00');
    $this->ana = User::factory()->create(['handle' => 'ana']);
    $this->actingAs($this->ana);
});

afterEach(fn () => Carbon::setTestNow());

function shotCallGame(string $id, string $etTime, array $attrs = []): Game
{
    return Game::create($attrs + [
        'id' => $id, 'season_id' => Season::first()->id, 'round_id' => null, 'phase' => 'REGULAR',
        'home_team_id' => test()->bos->id, 'away_team_id' => test()->lal->id,
        'start_time' => Carbon::parse($etTime, 'America/New_York')->utc(), 'status' => 'SCHEDULED',
    ]);
}

/** Box score com pontos de cada jogador (o resto zerado). */
function shotCallBox(Game $game, array $points): void
{
    foreach ($points as $playerId => $pts) {
        PlayerGameStat::updateOrCreate(['game_id' => $game->id, 'player_id' => $playerId], [
            'team_id' => Player::find($playerId)->team_id, 'minutes' => '30:00', 'pts' => $pts,
        ]);
    }
}

function shotCallPick(array $overrides = []): array
{
    return $overrides + ['winner_team_id' => test()->bos->id, 'margin_band' => 'M6_10', 'mvp_player_id' => 1];
}

it('faz e edita o palpite antes do jogo', function () {
    $this->putJson("/api/games/{$this->game->id}/shot-call", shotCallPick())->assertOk()->assertJsonPath('data.status', 'OPEN');
    $this->putJson("/api/games/{$this->game->id}/shot-call", shotCallPick(['margin_band' => 'M1_5', 'mvp_player_id' => 3]))->assertOk();

    expect(GameShotCall::count())->toBe(1)
        ->and(GameShotCall::first())->margin_band->toBe('M1_5')->mvp_player_id->toBe(3);

    $this->getJson("/api/games/{$this->game->id}")->assertOk()
        ->assertJsonPath('data.shot_call.locked', false)
        ->assertJsonPath('data.shot_call.mine.mvp.name', 'LeBron James')
        ->assertJsonPath('data.shot_call.mine.correct', null)
        ->assertJsonCount(3, 'data.shot_call.candidates'); // Bam é do Miami: fica de fora
});

it('valida o palpite', function (array $data, string $field) {
    $this->putJson("/api/games/{$this->game->id}/shot-call", $data)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'time de fora do jogo' => [fn () => shotCallPick(['winner_team_id' => test()->mia->id]), 'winner_team_id'],
    'MVP de outro time' => [fn () => shotCallPick(['mvp_player_id' => 4]), 'mvp_player_id'],
    'margem inválida' => [fn () => shotCallPick(['margin_band' => 'M99']), 'margin_band'],
    'sem MVP' => [fn () => array_diff_key(shotCallPick(), ['mvp_player_id' => 1]), 'mvp_player_id'],
]);

it('trava quando o jogo começa', function () {
    $this->putJson("/api/games/{$this->game->id}/shot-call", shotCallPick())->assertOk();
    Carbon::setTestNow(Carbon::parse('2026-11-10 20:00', 'America/New_York'));

    $this->putJson("/api/games/{$this->game->id}/shot-call", shotCallPick(['mvp_player_id' => 2]))
        ->assertUnprocessable()->assertJsonValidationErrors('game');
    $this->getJson("/api/games/{$this->game->id}")->assertJsonPath('data.shot_call.locked', true)->assertJsonCount(0, 'data.shot_call.candidates');
});

it('ao vivo mostra a parcial: "se acabasse agora"', function () {
    $this->putJson("/api/games/{$this->game->id}/shot-call", shotCallPick())->assertOk();
    $this->game->update(['status' => 'LIVE', 'period' => 4, 'clock' => '03:10', 'home_score' => 99, 'away_score' => 91]);
    shotCallBox($this->game, [1 => 30, 2 => 12, 3 => 25]);
    app(ShotCallService::class)->lock($this->game);

    $this->getJson("/api/games/{$this->game->id}")->assertOk()
        ->assertJsonPath('data.shot_call.mine.status', 'LOCKED')
        ->assertJsonPath('data.shot_call.mine.provisional', true)
        ->assertJsonPath('data.shot_call.mine.markets', ['winner' => true, 'margin' => true, 'mvp' => true])
        ->assertJsonPath('data.shot_call.mine.correct', 3)
        ->assertJsonPath('data.shot_call.margin', 'M6_10')
        ->assertJsonPath('data.shot_call.mvp_leader.name', 'Jayson Tatum');
});

it('apura no fim do jogo: MVP é quem fez mais pontos fantasy', function () {
    $this->putJson("/api/games/{$this->game->id}/shot-call", shotCallPick(['mvp_player_id' => 3]))->assertOk();
    $bia = User::factory()->create();
    GameShotCall::create(['user_id' => $bia->id, 'game_id' => $this->game->id, 'winner_team_id' => $this->lal->id, 'margin_band' => 'M1_5', 'mvp_player_id' => 1, 'status' => 'LOCKED']);

    $this->game->update(['status' => 'FINAL', 'period' => 4, 'home_score' => 112, 'away_score' => 104]);
    shotCallBox($this->game, [1 => 20, 2 => 10, 3 => 35]);
    GameFinished::dispatch($this->game);

    expect(PlayerGameScore::where('game_id', $this->game->id)->count())->toBe(3);
    // Ana: BOS (+1), por 6–10 (+2), LeBron MVP (+3).
    expect(GameShotCall::where('user_id', $this->ana->id)->first())
        ->status->toBe('SETTLED')->correct->toBe(3)->points->toBe(6)->settled_at->not->toBeNull();
    // Bia errou tudo.
    expect(GameShotCall::where('user_id', $bia->id)->first())->correct->toBe(0)->points->toBe(0);

    // Com menos de 10 jogos apurados, a taxa ainda não conta.
    expect(UserAccuracy::where('user_id', $this->ana->id)->where('mode', 'GAME')->first())
        ->rate->toBeNull()->counted->toBe(1);

    $this->getJson("/api/games/{$this->game->id}")
        ->assertJsonPath('data.shot_call.mine.provisional', false)
        ->assertJsonPath('data.shot_call.mine.points', 6);
});

it('a taxa entra com 10 jogos e define o nível', function () {
    // 10 jogos: 9 com 3/3 e 1 com 0/3 = 27/30 = 90% (Diamante).
    for ($i = 0; $i < 10; $i++) {
        $game = shotCallGame('40100010'.$i, '2026-11-0'.($i % 9 + 1).' 20:00', ['status' => 'FINAL', 'home_score' => 110, 'away_score' => 102]);
        shotCallBox($game, [1 => 40, 3 => 10]);
        GameShotCall::create(['user_id' => $this->ana->id, 'game_id' => $game->id, 'status' => 'LOCKED',
            'winner_team_id' => $i === 0 ? $this->lal->id : $this->bos->id,
            'margin_band' => $i === 0 ? 'M16_PLUS' : 'M6_10',
            'mvp_player_id' => $i === 0 ? 3 : 1]);
        GameFinished::dispatch($game);
    }

    expect(UserAccuracy::where('user_id', $this->ana->id)->where('mode', 'GAME')->first())
        ->rate->toEqual(0.9)->counted->toBe(10)->tier->toBe('DIAMOND');
    // O perfil mostra a taxa.
    $this->getJson('/api/users/ana')->assertJsonPath('data.accuracy.GAME.tier', 'DIAMOND');
});

it('o calendário mostra o meu palpite em cada jogo', function () {
    shotCallGame('401000002', '2026-11-10 22:00');
    $this->putJson("/api/games/{$this->game->id}/shot-call", shotCallPick())->assertOk();

    $games = collect($this->getJson('/api/games?date=2026-11-10')->assertOk()->json('data'))->keyBy('id');

    expect($games['401000001']['my_shot_call'])->toMatchArray(['status' => 'OPEN', 'correct' => null])
        ->and($games['401000002']['my_shot_call'])->toBeNull();
});

it('o comando games:finish apura jogos que terminaram sem a fila', function () {
    $this->putJson("/api/games/{$this->game->id}/shot-call", shotCallPick())->assertOk();
    $this->game->update(['status' => 'FINAL', 'home_score' => 100, 'away_score' => 99]);
    shotCallBox($this->game, [1 => 30, 3 => 20]);

    $this->artisan('games:finish', ['--all' => true])->expectsOutputToContain('1 palpite(s) apurado(s)')->assertSuccessful();
    $this->artisan('games:finish', ['--all' => true])->expectsOutputToContain('0 palpite(s) apurado(s)')->assertSuccessful();

    // BOS venceu (+1) por 1 (errou a margem), Tatum MVP (+3).
    expect(GameShotCall::first())->correct->toBe(2)->points->toBe(4);
});
