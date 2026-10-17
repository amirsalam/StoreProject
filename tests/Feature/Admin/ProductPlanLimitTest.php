<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Tenancy\TenantContext;
use Database\Seeders\PlansSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The admin product CRUD honors PlanGate before persisting.
 *
 * "at limit" → 402 Payment Required (matches the rest of the platform's
 * upgrade-prompt convention).
 */
class ProductPlanLimitTest extends TestCase
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

    public function test_admin_can_create_within_plan_limit(): void
    {
        $tenant = $this->tenantOn(Plan::SLUG_FREE);
        app(TenantContext::class)->set($tenant);
        $admin = $this->makeAdminFor($tenant);

        // 0 existing products on the free plan — 10 cap means we can add 1.
        $this->actingAs($admin)
            ->post('/admin/products', $this->validPayload())
            ->assertRedirect('/admin/products');

        $this->assertSame(1, Product::query()->forTenant($tenant)->count());
    }

    public function test_admin_is_blocked_at_plan_product_cap(): void
    {
        $tenant = $this->tenantOn(Plan::SLUG_FREE);
        app(TenantContext::class)->set($tenant);
        $admin = $this->makeAdminFor($tenant);

        Product::factory()->count(10)->create();                          // exactly at limit

        $this->actingAs($admin)
            ->post('/admin/products', $this->validPayload())
            ->assertStatus(402);

        $this->assertSame(10, Product::query()->forTenant($tenant)->count());
    }

    public function test_unlimited_plan_skips_the_check(): void
    {
        $tenant = $this->tenantOn(Plan::SLUG_BUSINESS);                   // products: null
        app(TenantContext::class)->set($tenant);
        $admin = $this->makeAdminFor($tenant);

        Product::factory()->count(150)->create();

        $this->actingAs($admin)
            ->post('/admin/products', $this->validPayload(['slug' => 'unique-150-plus']))
            ->assertRedirect('/admin/products');

        $this->assertSame(151, Product::query()->forTenant($tenant)->count());
    }

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

        return $tenant->fresh();
    }

    private function makeAdminFor(Tenant $tenant): User
    {
        $admin = User::factory()->admin()->create();
        $tenant->users()->attach($admin->id, ['role' => Tenant::ROLE_OWNER, 'joined_at' => now()]);

        return $admin;
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Plan Limit Test Product',
            'slug' => 'plan-limit-test-product',
            'short_description' => null,
            'description' => null,
            'type' => Product::TYPE_DIGITAL_DOWNLOAD,
            'price' => '49.00',
            'sale_price' => null,
            'currency' => 'USD',
            'version' => null,
            'license_type' => null,
            'default_activation_limit' => 1,
            'download_limit' => null,
            'status' => Product::STATUS_DRAFT,
            'is_featured' => false,
            'seo_title' => null,
            'seo_description' => null,
            'thumbnail' => null,
            'category_id' => null,
        ], $overrides);
    }
}
