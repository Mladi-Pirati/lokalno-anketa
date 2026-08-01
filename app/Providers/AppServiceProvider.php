<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Keycloak\KeycloakExtendSocialite;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // TLS terminates at Caddy before the request crosses Traefik, so the
        // final hop to Laravel is HTTP even though the public site is HTTPS.
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        // Register the Keycloak Socialite provider.
        Event::listen(SocialiteWasCalled::class, KeycloakExtendSocialite::class.'@handle');
    }
}
