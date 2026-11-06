<?php

use App\Models\League;
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
        ->and($member->pivot->joined_round_number)->toBe(\App\Models\Round::current()->number);

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

it('responde 401 em /me sem login', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});
