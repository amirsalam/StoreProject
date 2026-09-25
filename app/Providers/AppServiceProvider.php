<?php

namespace App\Providers;

use App\Events\PaymentCompleted;
use App\Listeners\FulfillOrder;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        // Digital fulfillment (licenses + downloads) fans out from a
        // completed payment — see App\Listeners\FulfillOrder.
        Event::listen(PaymentCompleted::class, FulfillOrder::class);

        // Public license API (/api/v1/licenses/*): unauthenticated, so
        // throttle per client IP.
        RateLimiter::for('license-api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
