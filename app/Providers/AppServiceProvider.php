<?php

namespace App\Providers;

use App\Domain\Licensing\LicenseActivationService;
use App\Services\MailSettings;
use App\Services\SocialLoginSettings;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\Contracts\Factory;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One TenantContext per request. ResolveTenant middleware fills
        // it; everything downstream reads from it via the tenant() helper.
        $this->app->singleton(TenantContext::class);
    }

    public function boot(): void
    {
        // Event listeners in app/Listeners are wired by Laravel's event
        // discovery (type-hinted handle()). Don't also Event::listen() them
        // here — that registers them twice.

        // Public license API (/api/v1/licenses/*): unauthenticated, so
        // throttle per client IP.
        RateLimiter::for('license-api', fn (Request $request) => Limit::perMinute(LicenseActivationService::RATE_LIMIT_PER_MINUTE)->by($request->ip()));

        // SMTP settings saved in Admin → Email override .env — applied the
        // first time the mailer is used, so ordinary requests pay nothing.
        $this->app->resolving('mail.manager', fn () => $this->app->make(MailSettings::class)->apply());

        // Google / GitHub credentials saved in Admin → Social login override
        // .env — applied when Socialite is first used (login buttons only).
        $this->app->resolving(Factory::class, fn () => $this->app->make(SocialLoginSettings::class)->apply());
    }
}
