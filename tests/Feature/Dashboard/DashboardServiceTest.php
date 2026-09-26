<?php

namespace Tests\Feature\Dashboard;

use App\Domain\Dashboard\DashboardService;
use App\Models\DailyMetric;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_super_admin_gets_platform_layout(): void
    {
        $admin = User::factory()->admin()->create();

        $payload = app(DashboardService::class)->forUser($admin);

        $this->assertSame('super-admin', $payload['layout']);
        $this->assertNotEmpty($payload['widgets']);

        $keys = collect($payload['widgets'])->pluck('key')->all();
        $this->assertContains('total_revenue_30d', $keys);
        $this->assertContains('active_vendors', $keys);
        $this->assertContains('pending_approvals', $keys);
    }

    public function test_customer_gets_personal_layout(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $payload = app(DashboardService::class)->forUser($user);

        $this->assertSame('customer', $payload['layout']);

        $keys = collect($payload['widgets'])->pluck('key')->all();
        $this->assertContains('total_spent', $keys);
        $this->assertContains('wallet_balance', $keys);

        // Customer should NOT see admin widgets
        $this->assertNotContains('total_revenue_30d', $keys);
        $this->assertNotContains('active_vendors', $keys);
    }

    public function test_revenue_widget_reads_from_daily_metrics(): void
    {
        $admin = User::factory()->admin()->create();

        // Seed three days of revenue metrics at $50, $30, $20 (in cents).
        foreach ([5000, 3000, 2000] as $i => $cents) {
            DailyMetric::create([
                'tenant_id' => null,
                'metric_key' => DailyMetric::KEY_REVENUE_CENTS,
                'dimension_key' => 'total',
                'value' => $cents,
                'currency' => 'USD',
                'recorded_on' => now()->subDays($i)->toDateString(),
            ]);
        }

        $payload = app(DashboardService::class)->forUser($admin);
        $revenue = collect($payload['widgets'])->firstWhere('key', 'total_revenue_30d');

        $this->assertSame(10_000, $revenue['data']['value']);
        $this->assertSame('USD', $revenue['data']['currency']);
    }

    public function test_payload_is_cached(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(DashboardService::class);

        // First call populates the cache.
        $first = $service->forUser($admin);

        // Add new data — but the cached payload should still be returned.
        DailyMetric::create([
            'tenant_id' => null,
            'metric_key' => DailyMetric::KEY_REVENUE_CENTS,
            'dimension_key' => 'total',
            'value' => 999_999,
            'currency' => 'USD',
            'recorded_on' => now()->toDateString(),
        ]);

        $second = $service->forUser($admin);

        // Same payload (cached).
        $this->assertSame($first['widgets'], $second['widgets']);

        // Invalidate and re-read; new data is reflected.
        $service->invalidate(user: $admin);
        $third = $service->forUser($admin);
        $revenue = collect($third['widgets'])->firstWhere('key', 'total_revenue_30d');
        $this->assertSame(999_999, $revenue['data']['value']);
    }

    public function test_generated_at_timestamp_is_included(): void
    {
        $payload = app(DashboardService::class)->forUser(User::factory()->create());

        $this->assertArrayHasKey('generated_at', $payload);
        $this->assertNotEmpty($payload['generated_at']);
    }
}
