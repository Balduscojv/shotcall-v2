<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->withHeader('Origin', 'http://localhost:3000');
    Notification::fake();
});

/** Caminho e query que o site repassa para a API a partir do link do e-mail. */
function verifyApiPath(string $frontUrl): string
{
    parse_str((string) parse_url($frontUrl, PHP_URL_QUERY), $q);
    $id = $q['id'];
    $hash = $q['hash'];
    unset($q['id'], $q['hash']);

    return "/api/auth/verify-email/{$id}/{$hash}?".http_build_query($q);
}

it('esqueci a senha: manda o link para o site e troca a senha com o token', function () {
    $ana = User::factory()->create(['email' => 'ana@exemplo.com', 'password' => 'segredo123']);

    $this->postJson('/api/auth/forgot-password', ['email' => 'ANA@exemplo.com'])->assertOk();
    // E-mail sem conta: mesma resposta, nada enviado.
    $this->postJson('/api/auth/forgot-password', ['email' => 'ninguem@exemplo.com'])->assertOk();

    $token = null;
    Notification::assertSentTo($ana, ResetPassword::class, function (ResetPassword $n) use ($ana, &$token) {
        $token = $n->token;
        $url = $n->toMail($ana)->actionUrl;

        return str_starts_with($url, 'http://localhost:3000/redefinir-senha?') && str_contains($url, 'email=ana%40exemplo.com');
    });
    Notification::assertCount(1);

    $this->postJson('/api/auth/reset-password', ['token' => 'errado', 'email' => 'ana@exemplo.com', 'password' => 'novasenha1', 'password_confirmation' => 'novasenha1'])
        ->assertUnprocessable()->assertJsonValidationErrors('token');

    $this->postJson('/api/auth/reset-password', ['token' => $token, 'email' => 'ana@exemplo.com', 'password' => 'novasenha1', 'password_confirmation' => 'novasenha1'])
        ->assertOk();
    expect(Hash::check('novasenha1', $ana->fresh()->password))->toBeTrue();

    // O token só vale uma vez.
    $this->postJson('/api/auth/reset-password', ['token' => $token, 'email' => 'ana@exemplo.com', 'password' => 'outra1234', 'password_confirmation' => 'outra1234'])
        ->assertUnprocessable();
});

it('cadastro manda o link de confirmação, que confirma o e-mail pelo site', function () {
    $this->postJson('/api/auth/register', ['name' => 'Bia', 'handle' => 'bia', 'email' => 'bia@exemplo.com', 'password' => 'segredo123'])
        ->assertCreated()->assertJsonPath('data.email_verified', false);
    $bia = User::where('handle', 'bia')->first();

    $url = null;
    Notification::assertSentTo($bia, VerifyEmail::class, function (VerifyEmail $n) use ($bia, &$url) {
        $url = $n->toMail($bia)->actionUrl;

        return str_starts_with($url, 'http://localhost:3000/verificar-email?');
    });

    // Assinatura adulterada não passa.
    $this->getJson(str_replace('signature=', 'signature=x', verifyApiPath($url)))->assertForbidden();
    expect($bia->fresh()->hasVerifiedEmail())->toBeFalse();

    $this->getJson(verifyApiPath($url))->assertOk();
    expect($bia->fresh()->hasVerifiedEmail())->toBeTrue();
    $this->actingAs($bia->fresh())->getJson('/api/me')->assertJsonPath('data.email_verified', true);
});

it('reenvia o link de confirmação só para quem ainda não confirmou', function () {
    $ana = User::factory()->unverified()->create();
    $this->actingAs($ana)->postJson('/api/me/verification-email')->assertOk();
    Notification::assertSentTo($ana, VerifyEmail::class);

    $bia = User::factory()->create();
    $this->actingAs($bia)->postJson('/api/me/verification-email')->assertOk();
    Notification::assertNotSentTo($bia, VerifyEmail::class);
});

it('trocar o e-mail pede confirmação de novo', function () {
    $ana = User::factory()->create();
    $this->actingAs($ana)->patchJson('/api/me', ['email' => 'novo@exemplo.com'])->assertOk()->assertJsonPath('data.email_verified', false);
});
