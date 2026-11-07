<?php

namespace App\Providers;

use App\Integrations\Nba\DemoNbaProvider;
use App\Integrations\Nba\EspnClient;
use App\Integrations\Nba\GameDataProvider;
use App\Integrations\Nba\NbaCdnClient;
use App\Models\User;
use App\Services\Fantasy\FantasyScorer;
use App\Services\Fantasy\LineupValidator;
use App\Services\Fantasy\PriceVariation;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Pesos da pontuação fantasy (config/fantasy.php).
        $this->app->bind(FantasyScorer::class, fn () => new FantasyScorer(config('fantasy.scoring')));
        $this->app->bind(PriceVariation::class, fn () => new PriceVariation(config('fantasy.pricing')));
        $this->app->bind(LineupValidator::class, fn () => new LineupValidator(config('fantasy.lineup')));

        // Fonte dos jogos da NBA (config/services.php, NBA_PROVIDER).
        $this->app->singleton(GameDataProvider::class, fn () => match (config('services.nba.provider')) {
            'demo' => new DemoNbaProvider,
            'cdn' => new NbaCdnClient,
            default => new EspnClient,
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Admin tem acesso completo: passa em qualquer Gate/Policy antes da regra específica.
        Gate::before(fn (User $user) => $user->isAdmin() ? true : null);

        $this->configureAccountEmails();
    }

    /** E-mails de conta em português, com os links abrindo no site (Next), não na API. */
    private function configureAccountEmails(): void
    {
        $front = rtrim((string) config('app.frontend_url'), '/');

        $resetUrl = fn (User $user, string $token) => $front.'/redefinir-senha?'.http_build_query(['token' => $token, 'email' => $user->email]);
        ResetPassword::createUrlUsing($resetUrl);
        ResetPassword::toMailUsing(fn (User $user, string $token) => (new MailMessage)
            ->subject('Crie uma senha nova no ShotCall')
            ->greeting("Fala, {$user->name}!")
            ->line('Alguém (tomara que você) pediu uma senha nova para a sua conta.')
            ->action('Criar senha nova', $resetUrl($user, $token))
            ->line('O link vale por '.config('auth.passwords.users.expire').' minutos. Se não foi você, é só ignorar este e-mail.')
            ->salutation('Até a quadra, ShotCall'));

        // A assinatura é da URL relativa: o site repassa os parâmetros para /api pelo proxy.
        VerifyEmail::createUrlUsing(function (User $user) use ($front) {
            $signed = URL::temporarySignedRoute('verification.verify', now()->addDays(3), [
                'id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification()),
            ], absolute: false);
            parse_str((string) parse_url($signed, PHP_URL_QUERY), $query);

            return $front.'/verificar-email?'.http_build_query(['id' => $user->getKey(), 'hash' => sha1($user->getEmailForVerification())] + $query);
        });
        VerifyEmail::toMailUsing(fn (User $user, string $url) => (new MailMessage)
            ->subject('Confirme seu e-mail no ShotCall')
            ->greeting("Bem-vindo, {$user->name}!")
            ->line('Falta um passo: confirme que este e-mail é seu.')
            ->action('Confirmar e-mail', $url)
            ->line('O link vale por 3 dias. Se você não criou conta no ShotCall, ignore este e-mail.')
            ->salutation('Até a quadra, ShotCall'));
    }
}
