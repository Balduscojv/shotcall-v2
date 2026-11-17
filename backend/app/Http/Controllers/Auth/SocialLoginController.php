<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Cosmetic;
use App\Models\SocialAccount;
use App\Models\User;
use App\Services\Cosmetics\AwardService;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialUser;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

/**
 * Entrar com Google. As duas rotas são navegação do navegador (não fetch), passando pelo proxy do
 * Next; rodam com o grupo 'web' para ter sessão (o state do OAuth e o login ficam nela).
 */
class SocialLoginController extends Controller
{
    /** GET /auth/providers — quais logins sociais estão configurados. */
    public function providers(): JsonResponse
    {
        return response()->json(['data' => ['google' => filled(config('services.google.client_id'))]]);
    }

    /** GET /auth/google/redirect?voltar=/caminho */
    public function redirect(Request $request): RedirectResponse
    {
        abort_unless(filled(config('services.google.client_id')), 404);
        $request->session()->put('social.return', $this->safeReturn($request->query('voltar')));

        return Socialite::driver('google')->redirect();
    }

    /** GET /auth/google/callback */
    public function callback(Request $request, AwardService $awards): RedirectResponse
    {
        abort_unless(filled(config('services.google.client_id')), 404);
        $front = rtrim((string) config('app.frontend_url'), '/');

        try {
            $google = Socialite::driver('google')->user();
        } catch (Throwable) {
            return redirect($front.'/entrar?erro=google');
        }
        if (! $google->getEmail()) {
            return redirect($front.'/entrar?erro=google');
        }

        $user = $this->findOrCreate($google, $awards);
        $return = $request->session()->pull('social.return', '/perfil');

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect($front.$return);
    }

    /**
     * Conta já ligada ao Google; senão a conta com o mesmo e-mail (o Google confirmou que é dele);
     * senão uma conta nova, com o @ tirado do e-mail.
     */
    private function findOrCreate(SocialUser $google, AwardService $awards): User
    {
        $linked = SocialAccount::where('provider', 'google')->where('provider_id', $google->getId())->first();
        if ($linked) {
            return $linked->user;
        }

        return DB::transaction(function () use ($google, $awards) {
            $email = strtolower($google->getEmail());
            $user = User::where('email', $email)->first();
            $new = ! $user;

            if ($new) {
                $user = User::create([
                    'name' => Str::limit($google->getName() ?: Str::before($email, '@'), 60, ''),
                    'handle' => $this->freeHandle(Str::before($email, '@')),
                    'email' => $email,
                    'password' => Str::random(40), // sem senha própria: entra pelo Google ou pede "esqueci a senha"
                    'avatar_url' => $google->getAvatar(),
                ]);
                $awards->fire($user, Cosmetic::TRIGGER_SIGNUP, equipIfEmpty: true);
            }
            if (! $user->hasVerifiedEmail()) {
                // Conta com esse e-mail nunca confirmada: pode ter sido criada por outra pessoa
                // para esperar o dono chegar. A senha dela deixa de valer; o dono pede outra se quiser.
                if (! $new) {
                    $user->forceFill(['password' => Str::random(40), 'remember_token' => Str::random(60)]);
                }
                $user->markEmailAsVerified();
                event(new Verified($user));
            }
            $user->socialAccounts()->create(['provider' => 'google', 'provider_id' => $google->getId()]);

            return $user;
        });
    }

    /** "joao.silva+nba" → "joao_silva_nba"; se já existir, ganha um número no fim. */
    private function freeHandle(string $base): string
    {
        $base = Str::limit(trim(preg_replace('/[^a-z0-9_]+/', '_', Str::lower(Str::ascii($base))), '_'), 20, '');
        $base = strlen($base) >= 3 ? $base : 'jogador';

        $handle = $base;
        for ($i = 2; User::where('handle', $handle)->exists(); $i++) {
            $handle = $base.$i;
        }

        return $handle;
    }

    /** Só caminhos do próprio site, para o "voltar" não virar redirecionamento aberto. */
    private function safeReturn(mixed $path): string
    {
        return is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//') ? $path : '/perfil';
    }
}
