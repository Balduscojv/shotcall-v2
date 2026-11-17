<?php

use App\Models\Cosmetic;
use App\Models\Friendship;
use App\Models\League;
use App\Models\Mission;
use App\Models\User;
use App\Services\Cosmetics\AwardService;
use App\Services\Missions\MissionRunner;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::factory()->admin()->create(['handle' => 'chefe']);
    $this->ana = User::factory()->create(['handle' => 'ana']);
    $this->bia = User::factory()->create(['handle' => 'bia']);
});

/** Ana e Bia amigas, com $n ligas finalizadas juntas. */
function friendsWithLeagues(int $n): void
{
    Friendship::create(['requester_id' => test()->ana->id, 'addressee_id' => test()->bia->id, 'status' => Friendship::ACCEPTED, 'accepted_at' => now()]);
    foreach (range(1, $n) as $_) {
        $league = League::create([
            'slug' => Str::random(8), 'name' => 'Liga', 'color' => '#ff6b00', 'owner_id' => test()->ana->id,
            'invite_code' => Str::upper(Str::random(6)), 'competitions' => ['FANTASY'], 'finished_at' => now(),
        ]);
        $league->members()->attach([test()->ana->id, test()->bia->id]);
    }
}

function newTitle(array $overrides = []): string
{
    return test()->actingAs(test()->admin)->postJson('/api/admin/cosmetics', $overrides + [
        'name' => 'Dupla Dinâmica', 'slot' => 'TITLE', 'rarity' => 'EPIC', 'color' => '#22D3EE',
    ])->assertCreated()->json('data.key');
}

it('só admin mexe no catálogo e nas missões', function () {
    $this->actingAs($this->ana)->getJson('/api/admin/cosmetics')->assertForbidden();
    $this->actingAs($this->ana)->getJson('/api/admin/missions')->assertForbidden();
    $this->actingAs($this->ana)->postJson('/api/admin/missions', [])->assertForbidden();
});

it('lista o catálogo com quem tem e em que missão está', function () {
    $this->actingAs($this->admin)->getJson('/api/admin/cosmetics')->assertOk()
        ->assertJsonFragment(['key' => 'mission-clutch', 'active' => true, 'owners_count' => 0, 'secret' => true])
        ->assertJsonFragment(['missions' => [['id' => Mission::where('key', 'clutch')->value('id'), 'name' => 'Clutch']]]);
});

it('cria título com cor e banner com imagem', function () {
    Storage::fake('public');

    $key = newTitle();
    expect($key)->toBe('dupla-dinamica')
        ->and(Cosmetic::where('key', $key)->first())->color->toBe('#22d3ee')->source->toBe('ADMIN');

    $this->actingAs($this->admin)->postJson('/api/admin/cosmetics', ['name' => 'Dupla Dinâmica', 'slot' => 'TITLE', 'rarity' => 'RARE'])
        ->assertJsonValidationErrors(['color' => 'Escolha uma cor.']);
    $this->actingAs($this->admin)->postJson('/api/admin/cosmetics', ['name' => 'Céu', 'slot' => 'BANNER', 'rarity' => 'RARE'])
        ->assertJsonValidationErrors(['image' => 'Envie a imagem do banner.']);

    $res = $this->actingAs($this->admin)->post('/api/admin/cosmetics', [
        'name' => 'Céu de Fogo', 'slot' => 'BANNER', 'rarity' => 'LEGENDARY', 'image' => UploadedFile::fake()->image('ceu.jpg', 1600, 400),
    ], ['Accept' => 'application/json'])->assertCreated();

    $url = $res->json('data.image_url');
    expect($url)->toStartWith('/storage/cosmetics/ceu-de-fogo-');
    Storage::disk('public')->assertExists(Str::after($url, '/storage/'));
    expect(newTitle())->toBe('dupla-dinamica-2');

    // GIF é guardado como veio, para manter a animação.
    $gif = $this->actingAs($this->admin)->post('/api/admin/cosmetics', [
        'name' => 'Torcida', 'slot' => 'BANNER', 'rarity' => 'EPIC', 'image' => UploadedFile::fake()->image('torcida.gif', 600, 140),
    ], ['Accept' => 'application/json'])->assertCreated()->json('data.image_url');
    expect($gif)->toStartWith('/storage/cosmetics/torcida-')->toEndWith('.gif');
    Storage::disk('public')->assertExists(Str::after($gif, '/storage/'));

    // Moldura e título aceitam as quatro raridades.
    foreach (['COMMON', 'RARE', 'EPIC', 'LEGENDARY'] as $rarity) {
        $this->actingAs($this->admin)->postJson('/api/admin/cosmetics', [
            'name' => "Aro {$rarity}", 'slot' => 'FRAME', 'rarity' => $rarity, 'color' => '#ffc83d',
        ])->assertCreated()->assertJsonPath('data.rarity', $rarity);
    }
});

it('edita e desativa prêmio; prêmio desativado não sai mais por missão nem no cadastro', function () {
    $this->actingAs($this->admin)->postJson('/api/admin/cosmetics/mission-splash-brothers', ['name' => 'Splash Bros', 'active' => false])
        ->assertOk()->assertJsonPath('data.name', 'Splash Bros')->assertJsonPath('data.active', false);

    friendsWithLeagues(3);
    expect(app(MissionRunner::class)->friendshipAccepted($this->ana, $this->bia))->toBe(0);

    $this->actingAs($this->admin)->postJson('/api/admin/cosmetics/welcome-title', ['active' => false])->assertOk();
    $nova = User::factory()->create();
    app(AwardService::class)->fire($nova, Cosmetic::TRIGGER_SIGNUP, equipIfEmpty: true);
    expect($nova->cosmetics()->pluck('key'))->not->toContain('welcome-title')->toContain('welcome-title-ballhog');
});

it('só apaga prêmio que ninguém tem', function () {
    $key = newTitle();
    $this->actingAs($this->admin)->postJson('/api/admin/users/ana/cosmetics', ['key' => $key])->assertCreated();
    $this->actingAs($this->admin)->deleteJson("/api/admin/cosmetics/{$key}")->assertUnprocessable();

    $other = newTitle(['name' => 'Ninguém Tem']);
    $this->actingAs($this->admin)->deleteJson("/api/admin/cosmetics/{$other}")->assertOk();
    expect(Cosmetic::where('key', $other)->exists())->toBeFalse();
});

it('mostra as condições com os parâmetros', function () {
    $conditions = collect($this->actingAs($this->admin)->getJson('/api/admin/missions/conditions')->assertOk()->json('data'))->keyBy('key');

    expect($conditions->keys()->all())->toContain('clutch', 'cestinha_points', 'round_points', 'perfect_shot_calls', 'friends_leagues')
        ->and($conditions['friends_leagues']['params'][0])->toMatchArray(['name' => 'min_leagues', 'default' => 3]);
});

it('cria missão com condição, parâmetros e recompensas, e ela passa a valer', function () {
    $key = newTitle();
    $res = $this->actingAs($this->admin)->postJson('/api/admin/missions', [
        'name' => 'Dupla de Ouro', 'condition' => 'friends_leagues', 'params' => ['min_leagues' => 1],
        'rewards' => [$key], 'secret' => true, 'hint' => 'Jogue junto.',
    ])->assertCreated()
        ->assertJsonPath('data.key', 'dupla-de-ouro')
        ->assertJsonPath('data.condition_label', 'Amigos que finalizaram ligas juntos')
        ->assertJsonPath('data.params.min_leagues', 1)
        ->assertJsonPath('data.rewards.0.key', $key);

    // Aparece como ??? com a dica para quem não tem.
    $this->actingAs($this->ana)->getJson('/api/me/cosmetics')->assertJsonFragment(['hint' => 'Jogue junto.', 'rarity' => 'EPIC']);

    friendsWithLeagues(1);
    app(MissionRunner::class)->friendshipAccepted($this->ana, $this->bia);
    expect($this->ana->cosmetics()->pluck('key')->all())->toBe([$key])     // o Splash Brothers pede 3
        ->and($this->bia->cosmetics()->where('key', $key)->first()->pivot->unlock_reason)->toBe('Dupla de Ouro com @ana');

    $this->actingAs($this->admin)->getJson("/api/admin/missions/{$res->json('data.id')}")->assertOk()->assertJsonPath('data.hint', 'Jogue junto.');
});

it('valida a missão', function () {
    $this->actingAs($this->admin)->postJson('/api/admin/missions', [
        'name' => 'X', 'condition' => 'round_points', 'params' => ['min_points' => 0], 'rewards' => [], 'secret' => true,
    ])->assertJsonValidationErrors(['params.min_points', 'rewards', 'hint']);

    $this->actingAs($this->admin)->postJson('/api/admin/missions', [
        'name' => 'X', 'condition' => 'nao-existe', 'rewards' => ['mission-clutch'], 'secret' => false,
    ])->assertJsonValidationErrors(['condition' => 'Condição desconhecida.']);

    $this->actingAs($this->admin)->postJson('/api/admin/missions', [
        'name' => 'X', 'condition' => 'round_points', 'params' => ['min_points' => 100], 'rewards' => ['mission-clutch'],
        'secret' => false, 'starts_at' => '2026-12-01', 'ends_at' => '2026-11-01',
    ])->assertJsonValidationErrors(['ends_at']);
});

it('missão desativada, fora do período ou não secreta não aparece como ??? e não libera', function () {
    $key = newTitle();
    $mission = Mission::create([
        'key' => 'teste', 'name' => 'Teste', 'condition' => 'friends_leagues', 'params' => ['min_leagues' => 1],
        'secret' => true, 'hint' => 'Dica de teste', 'active' => false,
    ]);
    $mission->rewards()->attach(Cosmetic::where('key', $key)->value('id'));
    friendsWithLeagues(1);

    $locked = fn () => collect($this->actingAs($this->ana)->getJson('/api/me/cosmetics')->json('meta.locked'))->pluck('hint')->all();

    expect($locked())->not->toContain('Dica de teste')
        ->and(app(MissionRunner::class)->friendshipAccepted($this->ana, $this->bia))->toBe(0);

    $mission->update(['active' => true, 'ends_at' => now()->subDay()]);
    expect($locked())->not->toContain('Dica de teste')
        ->and(app(MissionRunner::class)->friendshipAccepted($this->ana, $this->bia))->toBe(0);

    $mission->update(['ends_at' => null, 'secret' => false]);
    expect($locked())->not->toContain('Dica de teste')
        ->and(app(MissionRunner::class)->friendshipAccepted($this->ana, $this->bia))->toBe(2);
});

it('edita e apaga missão sem tirar o prêmio de quem já cumpriu', function () {
    $mission = Mission::where('key', 'splash-brothers')->firstOrFail();
    $this->actingAs($this->admin)->putJson("/api/admin/missions/{$mission->id}", [
        'name' => 'Splash Brothers', 'condition' => 'friends_leagues', 'params' => ['min_leagues' => 1],
        'rewards' => ['mission-splash-brothers'], 'secret' => true, 'hint' => 'Forme amizades consistentes.',
    ])->assertOk()->assertJsonPath('data.params.min_leagues', 1);

    friendsWithLeagues(1);
    expect(app(MissionRunner::class)->friendshipAccepted($this->ana, $this->bia))->toBe(2);

    $this->actingAs($this->admin)->patchJson("/api/admin/missions/{$mission->id}/active", ['active' => false])
        ->assertOk()->assertJsonPath('data.active', false);
    $this->actingAs($this->ana)->patchJson("/api/admin/missions/{$mission->id}/active", ['active' => true])->assertForbidden();

    $this->actingAs($this->admin)->deleteJson("/api/admin/missions/{$mission->id}")->assertOk();
    expect($this->ana->cosmetics()->where('key', 'mission-splash-brothers')->exists())->toBeTrue();
});

it('missão de e-mail confirmado: premia quem já confirmou ao criar e quem confirma depois', function () {
    $key = newTitle(['name' => 'Verificado']);
    $carla = User::factory()->unverified()->create(['handle' => 'carla']);

    $this->actingAs($this->admin)->postJson('/api/admin/missions', [
        'name' => 'Confirme o e-mail', 'condition' => 'email_verified', 'params' => [], 'rewards' => [$key],
    ])->assertCreated()->assertJsonPath('meta.granted', 3); // admin, Ana e Bia já confirmaram

    $has = fn (User $u) => $u->cosmetics()->where('key', $key)->exists();
    expect($has($this->ana))->toBeTrue()->and($has($carla))->toBeFalse()
        ->and(app(MissionRunner::class)->emailVerified($carla))->toBe(0);

    $carla->markEmailAsVerified();
    event(new Verified($carla));
    expect($has($carla))->toBeTrue();
});
