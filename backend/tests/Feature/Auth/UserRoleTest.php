<?php

use App\Enums\UserRole;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Gate;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
});

it('toda conta nova nasce jogador, mesmo tentando mandar outro papel', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Ana', 'handle' => 'ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123', 'role' => 'admin',
    ])->assertCreated()->assertJsonPath('data.role', 'player');

    expect(User::where('handle', 'ana')->first()->role)->toBe(UserRole::Player);
});

it('mostra o papel para o próprio usuário, mas não para outros jogadores', function () {
    $me = User::factory()->moderator()->create();
    $other = User::factory()->create();

    $this->actingAs($me)->getJson('/api/me')->assertJsonPath('data.role', 'moderator');
    $this->actingAs($other)->getJson("/api/users/{$me->handle}")->assertJsonMissingPath('data.role');
});

it('admin passa em qualquer Gate', function () {
    Gate::define('mexer-nas-configuracoes', fn () => false);

    expect(Gate::forUser(User::factory()->admin()->create())->allows('mexer-nas-configuracoes'))->toBeTrue()
        ->and(Gate::forUser(User::factory()->moderator()->create())->allows('mexer-nas-configuracoes'))->toBeFalse()
        ->and(Gate::forUser(User::factory()->create())->allows('mexer-nas-configuracoes'))->toBeFalse();
});

it('só admin acessa a área de administração', function () {
    $this->actingAs(User::factory()->create())->getJson('/api/admin/users')->assertForbidden();
    $this->actingAs(User::factory()->moderator()->create())->getJson('/api/admin/users')->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->getJson('/api/admin/users')->assertOk();
});

it('admin muda o papel de outro usuário, mas não o próprio', function () {
    $admin = User::factory()->admin()->create();
    $player = User::factory()->create();

    $this->actingAs($admin)->patchJson("/api/admin/users/{$player->handle}/role", ['role' => 'moderator'])
        ->assertOk()->assertJsonPath('data.role', 'moderator');
    expect($player->fresh()->role)->toBe(UserRole::Moderator);

    $this->patchJson("/api/admin/users/{$player->handle}/role", ['role' => 'chefe'])->assertUnprocessable()
        ->assertJsonPath('errors.role.0', 'Papel inválido. Use admin, moderador ou jogador.');
    $this->patchJson("/api/admin/users/{$player->handle}/role", [])->assertUnprocessable()->assertJsonValidationErrors('role');
    $this->patchJson("/api/admin/users/{$admin->handle}/role", ['role' => 'player'])->assertUnprocessable()
        ->assertJsonPath('errors.role.0', 'Você não pode mudar o seu próprio papel.');
    $this->patchJson('/api/admin/users/nao_existe/role', ['role' => 'player'])->assertNotFound();
    expect($admin->fresh()->role)->toBe(UserRole::Admin);
});

it('admin busca usuários por nome ou @ e filtra por papel', function () {
    $admin = User::factory()->admin()->create(['name' => 'Chefe', 'handle' => 'chefe']);
    User::factory()->create(['name' => 'Ana Souza', 'handle' => 'ana_crava']);
    User::factory()->moderator()->create(['name' => 'Bruno', 'handle' => 'bruno_mod']);

    $this->actingAs($admin);

    $this->getJson('/api/admin/users?q=SOUZA')->assertOk()
        ->assertJsonCount(1, 'data')->assertJsonPath('data.0.handle', 'ana_crava')->assertJsonPath('data.0.role', 'player');
    $this->getJson('/api/admin/users?q=bruno_')->assertJsonCount(1, 'data');
    $this->getJson('/api/admin/users?role=moderator')->assertJsonCount(1, 'data')->assertJsonPath('data.0.handle', 'bruno_mod');
    $this->getJson('/api/admin/users?role=chefe')->assertUnprocessable()->assertJsonValidationErrors('role');
});

it('o middleware role libera o papel pedido e sempre o admin', function () {
    Route::middleware(['api', 'auth:sanctum', 'role:moderator'])->get('/api/_teste-moderacao', fn () => 'ok');

    $this->actingAs(User::factory()->create())->getJson('/api/_teste-moderacao')->assertForbidden();
    $this->actingAs(User::factory()->moderator()->create())->getJson('/api/_teste-moderacao')->assertOk();
    $this->actingAs(User::factory()->admin()->create())->getJson('/api/_teste-moderacao')->assertOk();
});

it('define o papel pelo terminal', function () {
    $user = User::factory()->create(['handle' => 'bia']);

    $this->artisan('user:role', ['handle' => '@bia', 'role' => 'admin'])->assertSuccessful();
    expect($user->fresh()->role)->toBe(UserRole::Admin);

    $this->artisan('user:role', ['handle' => 'bia', 'role' => 'rei'])->assertFailed();
    $this->artisan('user:role', ['handle' => 'ninguem', 'role' => 'player'])->assertFailed();
});
