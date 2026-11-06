<?php

use App\Models\League;
use App\Models\Round;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    // Simula o navegador vindo do Next, para o Sanctum tratar como SPA com sessão.
    $this->withHeader('Origin', 'http://localhost:3000');
});

it('cria conta, entra na Liga Global e já fica logado', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Ana Souza', 'handle' => '@Ana_Crava', 'email' => 'Ana@Exemplo.com', 'password' => 'segredo123',
    ])->assertCreated()->assertJsonPath('data.handle', 'ana_crava')->assertJsonPath('data.email', 'ana@exemplo.com');

    $user = User::where('handle', 'ana_crava')->firstOrFail();
    $global = League::where('is_global', true)->first();
    $member = $global->members()->whereKey($user->id)->first();

    expect($member)->not->toBeNull()
        ->and($member->pivot->joined_round_number)->toBe(Round::current()->number);

    $this->getJson('/api/me')->assertOk()->assertJsonPath('data.id', $user->id);
});

it('recusa @ e e-mail repetidos', function () {
    User::factory()->create(['handle' => 'ana', 'email' => 'ana@exemplo.com']);

    $this->postJson('/api/auth/register', [
        'name' => 'Outra', 'handle' => 'ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123',
    ])->assertUnprocessable()->assertJsonValidationErrors(['handle', 'email']);
});

it('entra com e-mail e senha e sai', function () {
    $user = User::factory()->create(['email' => 'bia@exemplo.com', 'password' => 'segredo123']);

    $this->postJson('/api/auth/login', ['email' => 'BIA@exemplo.com', 'password' => 'segredo123'])
        ->assertOk()->assertJsonPath('data.id', $user->id);
    $this->assertAuthenticatedAs($user);

    $this->postJson('/api/auth/logout')->assertNoContent();
    $this->assertGuest('web');
});

it('recusa senha errada', function () {
    User::factory()->create(['email' => 'bia@exemplo.com', 'password' => 'segredo123']);

    $this->postJson('/api/auth/login', ['email' => 'bia@exemplo.com', 'password' => 'errada'])
        ->assertUnprocessable()->assertJsonValidationErrors('email');
    $this->assertGuest('web');
});

it('a sessão acaba ao fechar o navegador; "Manter conectado" usa o cookie de lembrar', function () {
    User::factory()->create(['email' => 'bia@exemplo.com', 'password' => 'segredo123']);

    // Cookie de sessão sem data de validade = some quando o navegador fecha.
    $res = $this->postJson('/api/auth/login', ['email' => 'bia@exemplo.com', 'password' => 'segredo123'])->assertOk();
    expect($res->getCookie(config('session.cookie'), false)?->getExpiresTime())->toBe(0);
    $recaller = collect($res->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
    expect($recaller)->toBeNull();

    $this->postJson('/api/auth/logout');
    $res = $this->postJson('/api/auth/login', ['email' => 'bia@exemplo.com', 'password' => 'segredo123', 'remember' => true])->assertOk();
    $recaller = collect($res->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
    expect($recaller)->not->toBeNull()
        ->and($recaller->getExpiresTime())->toBeGreaterThan(now()->addDays(30)->timestamp);
});

it('entrar com uma sessão antiga aberta troca de conta em vez de travar', function () {
    $ana = User::factory()->create(['email' => 'ana@exemplo.com', 'password' => 'segredo123']);
    $bia = User::factory()->create(['email' => 'bia@exemplo.com', 'password' => 'segredo123']);

    $this->postJson('/api/auth/login', ['email' => 'ana@exemplo.com', 'password' => 'segredo123', 'remember' => true])->assertOk();
    $this->assertAuthenticatedAs($ana);

    // Mesmo navegador, sessão da Ana ainda viva: entrar como Bia funciona.
    $res = $this->postJson('/api/auth/login', ['email' => 'bia@exemplo.com', 'password' => 'segredo123'])
        ->assertOk()->assertJsonPath('data.id', $bia->id);
    $this->assertAuthenticatedAs($bia);

    // O "manter conectado" da Ana não fica para trás.
    $forgotten = collect($res->headers->getCookies())->first(fn ($c) => str_starts_with($c->getName(), 'remember_web_'));
    expect($forgotten?->getExpiresTime())->toBeLessThan(now()->timestamp);

    // Entrar de novo com a mesma conta também não dá erro.
    $this->postJson('/api/auth/login', ['email' => 'bia@exemplo.com', 'password' => 'segredo123'])->assertOk();
});

it('criar conta com uma sessão aberta entra na conta nova', function () {
    $ana = User::factory()->create();
    $this->actingAs($ana, 'web');

    $this->postJson('/api/auth/register', [
        'name' => 'Nova', 'handle' => 'nova', 'email' => 'nova@exemplo.com', 'password' => 'segredo123',
    ])->assertCreated();
    $this->assertAuthenticatedAs(User::where('handle', 'nova')->first());
});

it('responde 401 em /me sem login', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});
