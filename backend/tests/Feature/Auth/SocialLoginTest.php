<?php

use App\Models\League;
use App\Models\SocialAccount;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    config(['services.google.client_id' => 'id-teste', 'services.google.client_secret' => 'segredo', 'app.frontend_url' => 'http://localhost:3000']);
});

function fakeGoogle(string $id, string $email, string $name = 'João Silva'): void
{
    $user = (new GoogleUser)->map(['id' => $id, 'email' => $email, 'name' => $name, 'avatar' => 'https://lh3.googleusercontent.com/a/x']);
    Socialite::shouldReceive('driver->user')->andReturn($user);
}

it('sem GOOGLE_CLIENT_ID o login com Google fica desligado', function () {
    config(['services.google.client_id' => null]);

    $this->getJson('/api/auth/providers')->assertOk()->assertJsonPath('data.google', false);
    $this->get('/api/auth/google/redirect')->assertNotFound();
});

it('primeiro login com Google cria a conta com e-mail confirmado e entra', function () {
    fakeGoogle('g-1', 'Joao.Silva+nba@gmail.com');

    $this->get('/api/auth/google/callback')->assertRedirect('http://localhost:3000/perfil');

    $user = User::where('email', 'joao.silva+nba@gmail.com')->firstOrFail();
    expect($user->handle)->toBe('joao_silva_nba')
        ->and($user->name)->toBe('João Silva')
        ->and($user->hasVerifiedEmail())->toBeTrue()
        ->and(League::where('is_global', true)->first()->members()->whereKey($user->id)->exists())->toBeTrue();
    $this->assertAuthenticatedAs($user);
});

it('liga o Google à conta que já tem o mesmo e-mail, e depois entra direto', function () {
    $ana = User::factory()->unverified()->create(['email' => 'ana@gmail.com', 'handle' => 'ana', 'password' => 'quem-criou-sabe']);
    fakeGoogle('g-2', 'ana@gmail.com');

    $this->get('/api/auth/google/callback')->assertRedirect();
    expect(SocialAccount::where('provider_id', 'g-2')->value('user_id'))->toBe($ana->id)
        ->and($ana->fresh()->hasVerifiedEmail())->toBeTrue()
        // Conta nunca confirmada: a senha de quem a criou deixa de valer.
        ->and(Hash::check('quem-criou-sabe', $ana->fresh()->password))->toBeFalse()
        ->and(User::count())->toBe(2); // ana + conta de sistema

    auth()->guard('web')->logout();
    $this->get('/api/auth/google/callback')->assertRedirect();
    $this->assertAuthenticatedAs($ana);
    expect(SocialAccount::count())->toBe(1);
});

it('@ repetido ganha número', function () {
    User::factory()->create(['handle' => 'bia']);
    fakeGoogle('g-3', 'bia@gmail.com', 'Bia');

    $this->get('/api/auth/google/callback');
    expect(User::where('email', 'bia@gmail.com')->value('handle'))->toBe('bia2');
});

it('falha no Google volta para a tela de entrar', function () {
    Socialite::shouldReceive('driver->user')->andThrow(new RuntimeException('state inválido'));

    $this->get('/api/auth/google/callback')->assertRedirect('http://localhost:3000/entrar?erro=google');
    $this->assertGuest();
});
