<?php

namespace App\Providers;

use App\Tenancy\TenantContext;
use Illuminate\Support\ServiceProvider;

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
    }
}
