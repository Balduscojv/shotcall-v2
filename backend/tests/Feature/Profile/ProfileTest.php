<?php

use App\Models\Cosmetic;
use App\Models\Game;
use App\Models\GameShotCall;
use App\Models\League;
use App\Models\Player;
use App\Models\Round;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->ana = User::factory()->create(['handle' => 'ana']);
    $this->bia = User::factory()->create(['handle' => 'bia']);
});

function makeGame(string $id, string $status, string $start): Game
{
    $round = Round::current();

    return Game::create([
        'id' => $id, 'season_id' => $round->season_id, 'round_id' => $round->id, 'phase' => 'REGULAR',
        'home_team_id' => 1610612752, 'away_team_id' => 1610612765, 'start_time' => $start, 'status' => $status,
        'home_score' => 103, 'away_score' => 86,
    ]);
}

it('mostra o perfil com números das abas e a relação de amizade', function () {
    $this->actingAs($this->ana)->getJson('/api/users/bia')
        ->assertOk()
        ->assertJsonPath('data.handle', 'bia')
        ->assertJsonPath('data.friendship', 'NONE')
        ->assertJsonMissingPath('data.email')
        ->assertJsonStructure(['data' => ['counts' => ['shot_calls', 'awards', 'leagues', 'friends'], 'accuracy', 'banner_url']]);

    $this->actingAs($this->ana)->getJson('/api/users/ana')->assertJsonPath('data.friendship', 'SELF');
    $this->actingAs($this->ana)->getJson('/api/users/ninguem')->assertNotFound();
});

it('troca e remove foto e banner, recortando para o tamanho certo', function () {
    Storage::fake('public');

    $this->actingAs($this->ana)
        ->post('/api/me/avatar', ['image' => UploadedFile::fake()->image('eu.jpg', 800, 600)], ['Accept' => 'application/json'])
        ->assertOk();
    $avatar = $this->ana->fresh()->avatar_url;
    expect($avatar)->toStartWith('/storage/avatars/');
    $path = str($avatar)->after('/storage/')->toString();
    Storage::disk('public')->assertExists($path);
    [$w, $h] = getimagesizefromstring(Storage::disk("public")->get($path));
    expect([$w, $h])->toBe([512, 512]);

    $this->actingAs($this->ana)
        ->post('/api/me/banner', ['image' => UploadedFile::fake()->image('quadra.png', 1600, 900)], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.banner_url', fn ($url) => str_starts_with($url, '/storage/banners/'));

    $this->actingAs($this->ana)->deleteJson('/api/me/avatar')->assertOk()->assertJsonPath('data.avatar_url', null);
    Storage::disk('public')->assertMissing($path);
});

it('recusa imagem pequena ou que não é imagem', function () {
    Storage::fake('public');

    $this->actingAs($this->ana)
        ->post('/api/me/avatar', ['image' => UploadedFile::fake()->image('mini.png', 50, 50)], ['Accept' => 'application/json'])
        ->assertUnprocessable()->assertJsonValidationErrors('image');
    $this->actingAs($this->ana)
        ->post('/api/me/banner', ['image' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertUnprocessable();
});

it('pede, aceita e desfaz amizade', function () {
    $this->actingAs($this->ana)->postJson('/api/friends', ['handle' => '@Bia'])
        ->assertCreated()->assertJsonPath('data.status', 'SENT');

    $this->actingAs($this->bia)->getJson('/api/friends/requests')->assertJsonPath('data.received.0.handle', 'ana');
    $this->actingAs($this->bia)->getJson('/api/users/ana')->assertJsonPath('data.friendship', 'RECEIVED');

    $this->actingAs($this->bia)->postJson('/api/friends/ana/accept')->assertOk();
    $this->actingAs($this->ana)->getJson('/api/users/ana/friends')->assertJsonPath('data.0.handle', 'bia');
    $this->actingAs($this->ana)->getJson('/api/users/bia')->assertJsonPath('data.counts.friends', 1);

    $this->actingAs($this->ana)->deleteJson('/api/friends/bia')->assertNoContent();
    $this->actingAs($this->ana)->getJson('/api/users/bia')->assertJsonPath('data.friendship', 'NONE');
});

it('vira amizade quando os dois pedem', function () {
    $this->actingAs($this->ana)->postJson('/api/friends', ['handle' => 'bia']);
    $this->actingAs($this->bia)->postJson('/api/friends', ['handle' => 'ana'])->assertJsonPath('data.status', 'FRIENDS');
});

it('não deixa se adicionar nem adicionar @ inexistente', function () {
    $this->actingAs($this->ana)->postJson('/api/friends', ['handle' => 'ana'])->assertUnprocessable();
    $this->actingAs($this->ana)->postJson('/api/friends', ['handle' => 'zzz'])
        ->assertUnprocessable()->assertJsonPath('errors.handle.0', 'Ninguém com esse @ por aqui.');
});

it('esconde palpites de outra pessoa até o jogo começar', function () {
    Player::create(['id' => 1, 'team_id' => 1610612752, 'full_name' => 'Jalen Brunson', 'position' => 'G']);
    makeGame('0022600001', 'FINAL', now()->subDay()->toDateTimeString());
    makeGame('0022600002', 'SCHEDULED', now()->addDay()->toDateTimeString());

    foreach (['0022600001', '0022600002'] as $game) {
        GameShotCall::create([
            'user_id' => $this->bia->id, 'game_id' => $game, 'winner_team_id' => 1610612752,
            'margin_band' => 'M16_PLUS', 'mvp_player_id' => 1,
        ]);
    }

    $this->actingAs($this->bia)->getJson('/api/users/bia/shot-calls')->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.game.id', '0022600002');
    $this->actingAs($this->ana)->getJson('/api/users/bia/shot-calls')->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.pick.winner', 'NYK')->assertJsonPath('data.0.pick.mvp', 'Jalen Brunson')
        ->assertJsonPath('data.0.game.home.score', 103);
});

it('lista ligas visíveis e prêmios conquistados', function () {
    $privada = League::create([
        'slug' => 'quadra', 'name' => 'Quadra', 'color' => '#22D3EE', 'owner_id' => $this->bia->id,
        'invite_code' => 'ABC123', 'competitions' => ['FANTASY'],
    ]);
    $privada->members()->attach($this->bia->id, ['role' => 'OWNER']);
    League::where('is_global', true)->first()->members()->attach($this->bia->id, ['role' => 'MEMBER']);

    $this->actingAs($this->bia)->getJson('/api/users/bia/leagues')->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.is_global', true)->assertJsonPath('data.1.role', 'OWNER');
    $this->actingAs($this->ana)->getJson('/api/users/bia/leagues')->assertJsonCount(1, 'data');

    $this->bia->cosmetics()->attach(Cosmetic::where('key', 'game-silver-frame')->value('id'), [
        'unlocked_at' => now(), 'unlock_reason' => '70% nos últimos 20 jogos',
    ]);
    $this->actingAs($this->ana)->getJson('/api/users/bia/awards')
        ->assertJsonPath('data.0.name', 'Moldura Prata')
        ->assertJsonPath('data.0.reason', '70% nos últimos 20 jogos')
        ->assertJsonPath('meta.collection_total', Cosmetic::count());
});
