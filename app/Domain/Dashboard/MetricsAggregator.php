<?php

namespace App\Domain\Dashboard;

use App\Models\DailyMetric;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Rolls the OLTP store (orders / payments / users / subscriptions)
 * into the daily_metrics table.
 *
 * Idempotent thanks to UNIQUE(tenant_id, metric_key, dimension_key,
 * recorded_on) — re-running for a day replaces that day's rows via
 * upsert. Safe to invoke from a schedule and from event listeners.
 */
class MetricsAggregator
{
    /**
     * Rebuild daily_metrics for the given date range, inclusive.
     *
     * @return int Number of (tenant_id, metric_key, recorded_on) rows written.
     */
    public function rebuildRange(CarbonImmutable $from, CarbonImmutable $to): int
    {
        $written = 0;
        for ($d = $from; $d->lte($to); $d = $d->addDay()) {
            $written += $this->rebuildDay($d);
        }
        return $written;
    }

    /**
     * Rebuild every daily_metric for the given day across every tenant
     * (and the platform-wide null-tenant rollup).
     */
    public function rebuildDay(CarbonImmutable $day): int
    {
        $written = 0;

        // Per-tenant metrics.
        Tenant::query()->chunkById(200, function ($tenants) use ($day, &$written) {
            foreach ($tenants as $tenant) {
                $written += $this->rebuildTenantDay($tenant->id, $day);
            }
        });

        // Platform-wide metrics (tenant_id = NULL).
        $written += $this->rebuildPlatformDay($day);

        return $written;
    }

    private function rebuildTenantDay(int $tenantId, CarbonImmutable $day): int
    {
        $start = $day->startOfDay();
        $end = $day->endOfDay();

        // Revenue in cents — sum of paid orders for this tenant for this day.
        $revenueCents = (int) round(((float) Order::query()
            ->where('tenant_id', $tenantId)
            ->where('status', Order::STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end])
            ->sum('total')) * 100);

        // Order count.
        $ordersCount = (int) Order::query()
            ->where('tenant_id', $tenantId)
            ->where('status', Order::STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end])
            ->count();

        // New customers (users created via this tenant via the tenant_user pivot).
        $newCustomers = (int) $this->safeQuery(
            fn () => DB::table('tenant_user')
                ->where('tenant_id', $tenantId)
                ->whereBetween('created_at', [$start, $end])
                ->count(),
        );

        // Refunds.
        $refundsCents = (int) round(((float) Payment::query()
            ->where('tenant_id', $tenantId)
            ->where('status', Payment::STATUS_REFUNDED)
            ->whereBetween('updated_at', [$start, $end])
            ->sum('amount')) * 100);

        return $this->upsertMany([
            [$tenantId, DailyMetric::KEY_REVENUE_CENTS, 'total', $revenueCents, 'USD', $day],
            [$tenantId, DailyMetric::KEY_ORDERS_COUNT, 'total', $ordersCount, null, $day],
            [$tenantId, DailyMetric::KEY_NEW_CUSTOMERS, 'total', $newCustomers, null, $day],
            [$tenantId, DailyMetric::KEY_REFUNDS_CENTS, 'total', $refundsCents, 'USD', $day],
        ]);
    }

    private function rebuildPlatformDay(CarbonImmutable $day): int
    {
        $start = $day->startOfDay();
        $end = $day->endOfDay();

        $vendorSignups = (int) Tenant::query()
            ->whereBetween('created_at', [$start, $end])
            ->count();

        $totalRevenueCents = (int) round(((float) Order::query()
            ->where('status', Order::STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end])
            ->sum('total')) * 100);

        $totalOrders = (int) Order::query()
            ->where('status', Order::STATUS_PAID)
            ->whereBetween('paid_at', [$start, $end])
            ->count();

        return $this->upsertMany([
            [null, DailyMetric::KEY_VENDOR_SIGNUPS, 'total', $vendorSignups, null, $day],
            [null, DailyMetric::KEY_REVENUE_CENTS, 'total', $totalRevenueCents, 'USD', $day],
            [null, DailyMetric::KEY_ORDERS_COUNT, 'total', $totalOrders, null, $day],
        ]);
    }

    /**
     * Bulk upsert. Pass tuples of (tenant_id, metric_key, dimension_key, value, currency, recorded_on).
     *
     * @param  array<int, array{0:?int, 1:string, 2:string, 3:int, 4:?string, 5:CarbonImmutable}>  $rows
     */
    private function upsertMany(array $rows): int
    {
        if (empty($rows)) {
            return 0;
        }

        $records = array_map(fn (array $r) => [
            'tenant_id' => $r[0],
            'metric_key' => $r[1],
            'dimension_key' => $r[2],
            'value' => $r[3],
            'currency' => $r[4],
            'recorded_on' => $r[5]->toDateString(),
            'updated_at' => now(),
            'created_at' => now(),
        ], $rows);

        // Upsert on the unique key — re-runs are idempotent.
        DailyMetric::query()->upsert(
            $records,
            uniqueBy: ['tenant_id', 'metric_key', 'dimension_key', 'recorded_on'],
            update: ['value', 'currency', 'updated_at'],
        );

        return count($records);
    }

    private function safeQuery(\Closure $fn): mixed
    {
        try {
            return $fn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
