<?php

namespace App\Listeners;

use App\Domain\Dashboard\DashboardService;
use App\Domain\Dashboard\MetricsAggregator;
use App\Events\PaymentCompleted;
use App\Events\PaymentRefunded;
use Carbon\CarbonImmutable;

/**
 * Keep the dashboard's revenue/order figures live: they are read from the
 * daily_metrics rollup, which otherwise only refreshes when the scheduler
 * runs (every 15 min — and not at all if `schedule:work` isn't running,
 * as in local development). Rebuilds today's rollup and busts the cached
 * dashboards whenever money moves.
 *
 * Wired by event discovery; a failure here must never undo a payment.
 */
class RefreshDashboardMetrics
{
    public function __construct(
        private readonly MetricsAggregator $metrics,
        private readonly DashboardService $dashboards,
    ) {}

    public function handle(PaymentCompleted|PaymentRefunded $event): void
    {
        try {
            $this->metrics->rebuildDay(CarbonImmutable::today());
            $this->dashboards->invalidate();
        } catch (\Throwable $e) {
            report($e); // the scheduled rollup will catch up
        }
    }
}
