<?php

namespace Tests\Feature\Plans;

use App\Domain\Plans\PlanGate;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlansSeeder::class);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->set(null);
        parent::tearDown();
    }

    public function test_features_on_pro_plan(): void
    {
        $tenant = $this->tenantOn(Plan::SLUG_PRO);
        $gate = app(PlanGate::class);

        $this->assertTrue($gate->allows($tenant, 'api_access'));
        $this->assertTrue($gate->allows($tenant, 'custom_branding'));
        $this->assertFalse($gate->allows($tenant, 'sso'));                // sso is business+
        $this->assertFalse($gate->allows($tenant, 'audit_export'));       // enterprise only
    }

    public function test_features_on_business_plan(): void
    {
        $tenant = $this->tenantOn(Plan::SLUG_BUSINESS);
        $gate = app(PlanGate::class);

        $this->assertTrue($gate->allows($tenant, 'sso'));
        $this->assertTrue($gate->allows($tenant, 'custom_domains'));
        $this->assertFalse($gate->allows($tenant, 'audit_export'));
    }

    public function test_features_on_enterprise_plan(): void
    {
        $tenant = $this->tenantOn(Plan::SLUG_ENTERPRISE);
        $gate = app(PlanGate::class);

        $this->assertTrue($gate->allows($tenant, 'audit_export'));
        $this->assertTrue($gate->allows($tenant, 'dedicated_schema'));
        $this->assertTrue($gate->allows($tenant, 'sla'));
    }

    public function test_within_limit_at_boundary_for_free_plan(): void
    {
        $tenant = $this->tenantOn(Plan::SLUG_FREE);
        app(TenantContext::class)->set($tenant);
        $gate = app(PlanGate::class);

        // Free plan caps products at 10.
        Product::factory()->count(9)->create();
        $this->assertTrue($gate->withinLimit($tenant, 'products', 1));     // 9 + 1 = 10, equal to limit ✓

        Product::factory()->create();                                     // now at 10
        $this->assertFalse($gate->withinLimit($tenant, 'products', 1));    // 10 + 1 > 10 ✗
        $this->assertTrue($gate->withinLimit($tenant, 'products', 0));     // adding 0 is always fine
    }

    public function test_unlimited_resource_always_passes(): void
    {
        $tenant = $this->tenantOn(Plan::SLUG_BUSINESS);                   // products: null
        app(TenantContext::class)->set($tenant);
        $gate = app(PlanGate::class);

        Product::factory()->count(50)->create();
        $this->assertTrue($gate->withinLimit($tenant, 'products', 1000));
    }

    public function test_no_active_subscription_defaults_to_allow(): void
    {
        // Lenient default — a tenant in the middle of a plan change should
        // not get hard-blocked. The provisioner is responsible for ensuring
        // every tenant has a subscription row.
        $tenant = Tenant::factory()->create();
        $gate = app(PlanGate::class);

        $this->assertTrue($gate->allows($tenant, 'sso'));
        $this->assertTrue($gate->withinLimit($tenant, 'products', 9_999));
    }

    public function test_unknown_resource_treats_count_as_zero(): void
    {
        $tenant = $this->tenantOn(Plan::SLUG_FREE);
        app(TenantContext::class)->set($tenant);
        $gate = app(PlanGate::class);

        // 'projects' isn't a real counter yet — UsageReader returns 0
        // so the limit is purely the plan's declared cap.
        $this->assertTrue($gate->withinLimit($tenant, 'projects', 3));     // 0 + 3 == 3 (free limit) ✓
        $this->assertFalse($gate->withinLimit($tenant, 'projects', 4));    // 0 + 4 > 3 ✗
    }

    public function test_snapshot_returns_used_limit_remaining_per_resource(): void
    {
        $tenant = $this->tenantOn(Plan::SLUG_FREE);
        app(TenantContext::class)->set($tenant);
        Product::factory()->count(3)->create();

        $snapshot = app(PlanGate::class)->snapshot($tenant);

        $this->assertArrayHasKey('products', $snapshot);
        $this->assertSame(['used' => 3, 'limit' => 10, 'remaining' => 7], $snapshot['products']);
        $this->assertArrayHasKey('users', $snapshot);                     // also present
    }

    public function test_default_plan_is_the_starter_plan(): void
    {
        $this->assertSame(Plan::SLUG_FREE, Plan::default()?->slug);
    }

    /**
     * Provision a tenant attached to the given plan with an active sub.
     */
    private function tenantOn(string $planSlug): Tenant
    {
        $owner = User::factory()->create();
        $tenant = Tenant::factory()->create(['owner_id' => $owner->id]);
        $plan = Plan::query()->where('slug', $planSlug)->first();

        TenantSubscription::create([
            'tenant_id' => $tenant->id,
            'plan_id' => $plan->id,
            'status' => TenantSubscription::STATUS_ACTIVE,
            'billing_cycle' => TenantSubscription::CYCLE_MONTHLY,
            'current_period_start' => now()->subDay(),
            'current_period_end' => now()->addMonth(),
        ]);

        // Refresh so currentSubscription relation resolves.
        return $tenant->fresh();
    }
}
