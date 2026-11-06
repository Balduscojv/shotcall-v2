<?php

use App\Models\Cosmetic;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');

    // Prêmio exclusivo: sem gatilho, só a moderação libera.
    $this->exclusive = Cosmetic::create([
        'key' => 'test-exclusive-banner', 'name' => 'Banner Exclusivo', 'slot' => 'BANNER',
        'rarity' => 'LEGENDARY', 'source' => 'SPECIAL', 'trigger' => null, 'asset_key' => 'test-exclusive-banner',
    ]);
});

function signUp(string $handle): User
{
    test()->postJson('/api/auth/register', [
        'name' => ucfirst($handle), 'handle' => $handle, 'email' => "{$handle}@exemplo.com", 'password' => 'segredo123',
    ])->assertCreated();

    return User::where('handle', $handle)->firstOrFail();
}

it('libera os prêmios de boas-vindas e equipa um de cada tipo no cadastro', function () {
    $user = signUp('ana');

    expect($user->cosmetics()->pluck('key')->sort()->values()->all())
        ->toBe([
            'welcome-banner', 'welcome-banner-arena', 'welcome-banner-street',
            'welcome-frame', 'welcome-frame-paint', 'welcome-frame-parquet',
            'welcome-title', 'welcome-title-ballhog', 'welcome-title-sixth',
        ])
        ->and($user->cosmetics()->first()->pivot->unlock_reason)->toBe('Boas-vindas à quadra')
        ->and($user->cosmetics()->first()->pivot->granted_by)->toBeNull();

    $this->getJson('/api/me')->assertOk()
        ->assertJsonPath('data.equipped.banner.key', 'welcome-banner')
        ->assertJsonPath('data.equipped.frame.key', 'welcome-frame')
        ->assertJsonPath('data.equipped.title.name', 'Rookie');
});

it('o perfil mostra o que o usuário está usando', function () {
    signUp('ana');
    $bia = User::factory()->create(['handle' => 'bia']);
    $this->flushSession();

    $this->actingAs($bia)->getJson('/api/users/ana')
        ->assertJsonPath('data.equipped.title.key', 'welcome-title')
        ->assertJsonPath('data.equipped.frame.asset_key', 'welcome-frame');
    $awards = $this->getJson('/api/users/ana/awards')->assertJsonCount(9, 'data')->json('data');
    // Um por tipo em uso; em cada tipo, o primeiro do catálogo.
    expect(collect($awards)->where('equipped', true)->pluck('key')->sort()->values()->all())
        ->toBe(['welcome-banner', 'welcome-frame', 'welcome-title']);
});

it('o comando libera o gatilho para quem já tinha conta, sem repetir', function () {
    $old = User::factory()->create(['handle' => 'antiga']);

    $this->artisan('awards:fire', ['trigger' => 'SIGNUP', '--equip' => true])->assertSuccessful();
    $this->artisan('awards:fire', ['trigger' => 'SIGNUP', '--user' => '@antiga'])
        ->expectsOutputToContain('0 prêmio(s)')->assertSuccessful();

    $old->refresh();
    expect($old->cosmetics()->count())->toBe(9)
        ->and($old->equipped_title_id)->toBe(Cosmetic::where('key', 'welcome-title')->value('id'));
});

it('equipa e tira prêmios que o jogador tem, um por tipo', function () {
    $ana = signUp('ana');

    $this->putJson('/api/me/cosmetics/title', ['key' => null])->assertOk()->assertJsonPath('data.equipped.title', null);
    $this->putJson('/api/me/cosmetics/title', ['key' => 'welcome-title'])->assertOk()
        ->assertJsonPath('data.equipped.title.key', 'welcome-title');

    // Prêmio de outro tipo, prêmio que não tem, prêmio que não existe e tipo que não existe.
    $this->putJson('/api/me/cosmetics/title', ['key' => 'welcome-frame'])->assertUnprocessable()
        ->assertJsonPath('errors.key.0', 'Esse prêmio não é do tipo Título.');
    $this->putJson('/api/me/cosmetics/banner', ['key' => 'test-exclusive-banner'])->assertUnprocessable()
        ->assertJsonPath('errors.key.0', 'Você ainda não liberou esse prêmio.');
    $this->putJson('/api/me/cosmetics/banner', ['key' => 'nao-existe'])->assertUnprocessable();
    $this->putJson('/api/me/cosmetics/cor', ['key' => null])->assertNotFound();
    $this->putJson('/api/me/cosmetics/banner', [])->assertUnprocessable()->assertJsonValidationErrors('key');

    expect($ana->fresh()->equipped_banner_id)->toBe(Cosmetic::where('key', 'welcome-banner')->value('id'));
    $this->getJson('/api/me/cosmetics')->assertJsonCount(9, 'data');

    // Trocar entre os banners de boas-vindas.
    $this->putJson('/api/me/cosmetics/banner', ['key' => 'welcome-banner-street'])->assertOk()
        ->assertJsonPath('data.equipped.banner.name', 'Quadra do Bairro');
});

it('admin tem acesso a todos os prêmios sem precisar liberar', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->getJson('/api/me/cosmetics')
        ->assertJsonCount(Cosmetic::count(), 'data')
        ->assertJsonFragment(['key' => 'test-exclusive-banner', 'owned' => false]);
    $this->putJson('/api/me/cosmetics/banner', ['key' => 'test-exclusive-banner'])->assertOk()
        ->assertJsonPath('data.equipped.banner.key', 'test-exclusive-banner');
});

it('admin libera e remove qualquer prêmio pela moderação', function () {
    $admin = User::factory()->admin()->create();
    $ana = signUp('ana');
    $this->flushSession();
    $this->actingAs($admin);

    $this->getJson('/api/admin/users/ana/cosmetics')->assertOk()
        ->assertJsonCount(Cosmetic::count(), 'data')
        ->assertJsonFragment(['key' => 'test-exclusive-banner', 'owned' => false, 'trigger' => null])
        ->assertJsonFragment(['key' => 'welcome-title', 'owned' => true, 'equipped' => true]);

    $this->postJson('/api/admin/users/ana/cosmetics', ['key' => 'test-exclusive-banner', 'reason' => 'Campeã do bolão'])
        ->assertCreated();
    $pivot = $ana->cosmetics()->where('key', 'test-exclusive-banner')->first()->pivot;
    expect($pivot->unlock_reason)->toBe('Campeã do bolão')->and($pivot->granted_by)->toBe($admin->id);

    $this->postJson('/api/admin/users/ana/cosmetics', ['key' => 'test-exclusive-banner'])->assertUnprocessable()
        ->assertJsonPath('errors.key.0', 'Esse usuário já tem esse prêmio.');
    $this->postJson('/api/admin/users/ana/cosmetics', ['key' => 'nao-existe'])->assertUnprocessable()
        ->assertJsonPath('errors.key.0', 'Prêmio não encontrado.');

    // Ana passa a poder usar; remover desequipa.
    $this->flushSession();
    $this->actingAs($ana)->putJson('/api/me/cosmetics/banner', ['key' => 'test-exclusive-banner'])->assertOk();
    $this->flushSession();
    $this->actingAs($admin)->deleteJson('/api/admin/users/ana/cosmetics/test-exclusive-banner')->assertOk();
    expect($ana->fresh()->equipped_banner_id)->toBeNull()
        ->and($ana->cosmetics()->where('key', 'test-exclusive-banner')->exists())->toBeFalse();
    $this->deleteJson('/api/admin/users/ana/cosmetics/test-exclusive-banner')->assertNotFound();
});

it('só admin mexe nos prêmios dos outros', function () {
    signUp('ana');
    $moderator = User::factory()->moderator()->create();
    $this->flushSession();

    $this->actingAs($moderator)->getJson('/api/admin/users/ana/cosmetics')->assertForbidden();
    $this->postJson('/api/admin/users/ana/cosmetics', ['key' => 'test-exclusive-banner'])->assertForbidden();
    $this->deleteJson('/api/admin/users/ana/cosmetics/welcome-title')->assertForbidden();
});

it('enviar um banner próprio tira o banner-prêmio de uso', function () {
    Storage::fake('public');
    signUp('ana');

    $this->post('/api/me/banner', ['image' => UploadedFile::fake()->image('b.jpg', 1600, 500)], ['Accept' => 'application/json'])
        ->assertOk()->assertJsonPath('data.banner_url', fn ($url) => $url !== null);

    expect(User::where('handle', 'ana')->first()->equipped_banner_id)->toBeNull();
});
