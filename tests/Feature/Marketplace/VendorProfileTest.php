<?php

namespace Tests\Feature\Marketplace;

use App\Domain\Marketplace\VendorService;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Vendor;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class VendorProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        app(TenantContext::class)->set(null);
        parent::tearDown();
    }

    public function test_guests_are_redirected_from_the_vendor_center(): void
    {
        $this->get('/workspace/vendor')->assertRedirect('/login');
    }

    public function test_user_without_a_store_sees_the_open_store_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/workspace/vendor')
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('workspace/vendor/edit')
                    ->where('vendor', null)
            );
    }

    public function test_a_new_store_awaits_approval_by_default(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/workspace/vendor', ['name' => 'Acme Digital'])
            ->assertRedirect(route('workspace.vendor.edit'))
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'submitted for review'));

        $vendor = $user->vendor()->first();
        $this->assertNotNull($vendor);
        $this->assertSame('Acme Digital', $vendor->name);
        $this->assertSame(Vendor::STATUS_PENDING, $vendor->status);
        $this->assertNotNull($vendor->profile);

        // The owner sees their pending state (drives the status banner)…
        $this->actingAs($user)
            ->get('/workspace/vendor')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('vendor.status', Vendor::STATUS_PENDING)
                ->where('storeUrl', null)
            );

        // …and the public store stays hidden until an admin approves it.
        $this->get("/store/{$vendor->slug}")->assertNotFound();
    }

    public function test_a_new_store_goes_live_immediately_under_auto_approval(): void
    {
        config(['marketplace.vendor_approval_mode' => 'auto']);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/workspace/vendor', ['name' => 'Acme Digital'])
            ->assertSessionHas('success', fn (string $m) => str_contains($m, 'live'));

        $this->assertSame(Vendor::STATUS_ACTIVE, $user->vendor()->first()->status);
    }

    public function test_the_tenant_setting_overrides_the_platform_default(): void
    {
        config(['marketplace.vendor_approval_mode' => 'manual']);
        $tenant = Tenant::factory()->create([
            'settings' => ['marketplace' => ['vendor_approval_mode' => 'auto']],
        ]);
        app(TenantContext::class)->set($tenant);

        $vendor = app(VendorService::class)->registerForUser(User::factory()->create(), ['name' => 'Tenant Store']);

        $this->assertSame(Vendor::STATUS_ACTIVE, $vendor->status);
        $this->assertSame($tenant->id, $vendor->tenant_id);
    }

    public function test_an_unknown_approval_mode_falls_back_to_manual(): void
    {
        config(['marketplace.vendor_approval_mode' => 'yolo']);

        $vendor = app(VendorService::class)->registerForUser(User::factory()->create(), ['name' => 'Safe Default']);

        $this->assertSame(Vendor::STATUS_PENDING, $vendor->status);
    }

    public function test_opening_a_store_is_idempotent_per_user(): void
    {
        $user = User::factory()->create();
        Vendor::factory()->create(['owner_user_id' => $user->id]);

        $this->actingAs($user)
            ->post('/workspace/vendor', ['name' => 'Second Store'])
            ->assertRedirect(route('workspace.vendor.edit'));

        $this->assertSame(1, Vendor::query()->where('owner_user_id', $user->id)->count());
    }

    public function test_owner_can_update_their_profile(): void
    {
        $user = User::factory()->create();
        $vendor = Vendor::factory()->create(['owner_user_id' => $user->id]);

        $this->actingAs($user)
            ->put('/workspace/vendor', [
                'name' => 'Renamed Store',
                'bio' => 'We build great tools.',
                'website' => 'https://example.com',
                'country' => 'US',
            ])
            ->assertRedirect(route('workspace.vendor.edit'));

        $vendor->refresh()->load('profile');
        $this->assertSame('Renamed Store', $vendor->name);
        $this->assertSame('We build great tools.', $vendor->profile->bio);
        $this->assertSame('US', $vendor->profile->country);
    }

    public function test_profile_update_validates_input(): void
    {
        $user = User::factory()->create();
        Vendor::factory()->create(['owner_user_id' => $user->id]);

        $this->actingAs($user)
            ->put('/workspace/vendor', ['name' => '', 'website' => 'not-a-url'])
            ->assertSessionHasErrors(['name', 'website']);
    }

    public function test_policy_allows_owner_blocks_others_and_admins_pass(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $vendor = Vendor::factory()->create(['owner_user_id' => $owner->id]);

        $this->assertTrue($owner->can('update', $vendor));
        $this->assertFalse($other->can('update', $vendor));
        $this->assertTrue($admin->can('update', $vendor));
    }

    public function test_service_generates_unique_slugs_for_same_name(): void
    {
        $service = app(VendorService::class);

        $a = $service->registerForUser(User::factory()->create(), ['name' => 'Acme Co']);
        $b = $service->registerForUser(User::factory()->create(), ['name' => 'Acme Co']);

        $this->assertNotSame($a->slug, $b->slug);
        $this->assertSame('acme-co', $a->slug);
    }
}
