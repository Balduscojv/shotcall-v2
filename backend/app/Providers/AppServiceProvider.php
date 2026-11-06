<?php

namespace App\Providers;

use App\Integrations\Nba\DemoNbaProvider;
use App\Integrations\Nba\EspnClient;
use App\Integrations\Nba\GameDataProvider;
use App\Integrations\Nba\NbaCdnClient;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
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
    }
}
