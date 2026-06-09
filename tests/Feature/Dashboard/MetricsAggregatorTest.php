<?php

namespace Tests\Feature\Dashboard;

use App\Domain\Dashboard\MetricsAggregator;
use App\Models\DailyMetric;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsAggregatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_rebuild_day_aggregates_paid_orders_into_revenue_metric(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create();
        $today = CarbonImmutable::today();

        Order::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'status' => Order::STATUS_PAID,
            'paid_at' => $today,
            'total' => 49.00,
        ]);

        app(MetricsAggregator::class)->rebuildDay($today);

        $row = DailyMetric::query()
            ->where('tenant_id', $tenant->id)
            ->where('metric_key', DailyMetric::KEY_REVENUE_CENTS)
            ->where('recorded_on', $today->toDateString())
            ->first();

        $this->assertNotNull($row);
        $this->assertSame(9_800, $row->value);
    }

    public function test_rebuild_is_idempotent_via_upsert(): void
    {
        $tenant = Tenant::factory()->create();
        $today = CarbonImmutable::today();
        Order::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => Order::STATUS_PAID,
            'paid_at' => $today,
            'total' => 10.00,
        ]);

        $aggregator = app(MetricsAggregator::class);
        $aggregator->rebuildDay($today);
        $aggregator->rebuildDay($today);
        $aggregator->rebuildDay($today);

        $rows = DailyMetric::query()
            ->where('tenant_id', $tenant->id)
            ->where('metric_key', DailyMetric::KEY_REVENUE_CENTS)
            ->where('recorded_on', $today->toDateString())
            ->count();

        // Re-running must not multiply rows.
        $this->assertSame(1, $rows);
    }

    public function test_orders_outside_the_day_are_excluded(): void
    {
        $tenant = Tenant::factory()->create();
        $today = CarbonImmutable::today();

        Order::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => Order::STATUS_PAID,
            'paid_at' => $today->subDays(2),
            'total' => 99.00,
        ]);

        app(MetricsAggregator::class)->rebuildDay($today);

        $row = DailyMetric::query()
            ->where('tenant_id', $tenant->id)
            ->where('metric_key', DailyMetric::KEY_REVENUE_CENTS)
            ->where('recorded_on', $today->toDateString())
            ->first();

        $this->assertSame(0, $row->value);
    }

    public function test_rebuild_range_covers_each_day_inclusive(): void
    {
        $tenant = Tenant::factory()->create();
        $today = CarbonImmutable::today();

        // 3 orders, one per day across the last 3 days.
        for ($i = 0; $i < 3; $i++) {
            Order::factory()->create([
                'tenant_id' => $tenant->id,
                'status' => Order::STATUS_PAID,
                'paid_at' => $today->subDays($i),
                'total' => 10.00,
            ]);
        }

        app(MetricsAggregator::class)->rebuildRange($today->subDays(2), $today);

        $rows = DailyMetric::query()
            ->where('tenant_id', $tenant->id)
            ->where('metric_key', DailyMetric::KEY_REVENUE_CENTS)
            ->orderBy('recorded_on')
            ->get();

        $this->assertCount(3, $rows);
        $this->assertSame(1000, $rows->sum('value'));
    }
}
