<?php

use App\Models\FantasyTeam;
use App\Models\League;
use App\Models\User;
use App\Services\Leagues\LeagueService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    $this->ana = User::factory()->create(['handle' => 'ana', 'email' => 'ana@exemplo.com', 'password' => 'segredo123']);
});

it('edita nome, @ e e-mail', function () {
    User::factory()->create(['handle' => 'bia', 'email' => 'bia@exemplo.com']);
    $this->actingAs($this->ana);

    $this->patchJson('/api/me', ['name' => 'Ana Lima', 'handle' => '@Ana_L', 'email' => 'ANA@novo.com'])->assertOk()
        ->assertJsonPath('data.name', 'Ana Lima')
        ->assertJsonPath('data.handle', 'ana_l')
        ->assertJsonPath('data.email', 'ana@novo.com');

    // O próprio @ não conta como repetido; o de outra pessoa, sim.
    $this->patchJson('/api/me', ['handle' => 'ana_l'])->assertOk();
    $this->patchJson('/api/me', ['handle' => 'bia', 'email' => 'bia@exemplo.com'])->assertUnprocessable()->assertJsonValidationErrors(['handle', 'email']);
});

it('troca a senha só com a senha atual certa', function () {
    $this->actingAs($this->ana);

    $this->putJson('/api/me/password', ['current_password' => 'errada', 'password' => 'novasenha1', 'password_confirmation' => 'novasenha1'])
        ->assertUnprocessable()->assertJsonValidationErrors('current_password');
    $this->putJson('/api/me/password', ['current_password' => 'segredo123', 'password' => 'novasenha1', 'password_confirmation' => 'outra'])
        ->assertUnprocessable()->assertJsonValidationErrors('password');

    $this->putJson('/api/me/password', ['current_password' => 'segredo123', 'password' => 'novasenha1', 'password_confirmation' => 'novasenha1'])->assertNoContent();
    expect(Hash::check('novasenha1', $this->ana->fresh()->password))->toBeTrue();
});

it('exclui a conta: some do sistema e passa as ligas adiante', function () {
    $bia = User::factory()->create(['handle' => 'bia']);
    $leagues = app(LeagueService::class);
    $shared = $leagues->create($this->ana, ['name' => 'Firma', 'color' => '#22D3EE', 'privacy' => 'PRIVATE', 'competitions' => ['FANTASY']]);
    $leagues->join($bia, $shared);
    $alone = $leagues->create($this->ana, ['name' => 'Só eu', 'color' => '#22D3EE', 'privacy' => 'PRIVATE', 'competitions' => ['FANTASY']]);

    $this->actingAs($this->ana);
    $this->deleteJson('/api/me', ['password' => 'errada'])->assertUnprocessable()->assertJsonValidationErrors('password');
    expect(User::find($this->ana->id))->not->toBeNull();

    $this->deleteJson('/api/me', ['password' => 'segredo123'])->assertNoContent();

    expect(User::find($this->ana->id))->toBeNull()
        ->and(League::find($alone->id))->toBeNull()
        ->and($shared->fresh()->owner_id)->toBe($bia->id)
        ->and($shared->members()->whereKey($bia->id)->first()->pivot->role)->toBe('OWNER')
        ->and(League::where('is_global', true)->first()->members()->whereKey($this->ana->id)->exists())->toBeFalse();
});

it('a conta de sistema não pode ser excluída', function () {
    $system = User::where('handle', 'shotcall')->first();
    $system->forceFill(['password' => 'segredo123'])->save();
    $this->actingAs($system);

    $this->deleteJson('/api/me', ['password' => 'segredo123'])->assertUnprocessable();
    expect(User::find($system->id))->not->toBeNull();
});
