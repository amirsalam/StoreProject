<?php

namespace App\Providers;

use App\Events\PaymentCompleted;
use App\Listeners\FulfillOrder;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Event;
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
    }
}
