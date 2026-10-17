<?php

namespace Tests\Feature\Marketplace;

use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class VendorStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_store_renders_active_vendor_with_published_products(): void
    {
        $vendor = Vendor::factory()->create();
        VendorProfile::factory()->create(['vendor_id' => $vendor->id]);

        Product::factory()->count(2)->create([
            'vendor_id' => $vendor->id,
            'status' => Product::STATUS_PUBLISHED,
        ]);
        // A draft must not appear in the public store.
        Product::factory()->create([
            'vendor_id' => $vendor->id,
            'status' => Product::STATUS_DRAFT,
        ]);

        $this->get("/store/{$vendor->slug}")
            ->assertOk()
            ->assertInertia(
                fn (AssertableInertia $page) => $page
                    ->component('store/show')
                    ->where('vendor.slug', $vendor->slug)
                    ->has('products.data', 2)
                    ->where('productsCount', 2)
            );
    }

    public function test_pending_vendor_store_is_not_public(): void
    {
        $vendor = Vendor::factory()->pending()->create();

        $this->get("/store/{$vendor->slug}")->assertNotFound();
    }

    public function test_suspended_vendor_store_is_not_public(): void
    {
        $vendor = Vendor::factory()->suspended()->create();

        $this->get("/store/{$vendor->slug}")->assertNotFound();
    }

    public function test_unknown_store_slug_returns_404(): void
    {
        $this->get('/store/does-not-exist')->assertNotFound();
    }

    public function test_verified_badge_is_exposed_to_the_page(): void
    {
        $vendor = Vendor::factory()->verified()->create();

        $this->get("/store/{$vendor->slug}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('vendor.is_verified', true));
    }

    public function test_another_vendors_products_do_not_leak_into_a_store(): void
    {
        $vendorA = Vendor::factory()->create();
        $vendorB = Vendor::factory()->create();

        Product::factory()->create(['vendor_id' => $vendorA->id, 'status' => Product::STATUS_PUBLISHED]);
        Product::factory()->count(3)->create(['vendor_id' => $vendorB->id, 'status' => Product::STATUS_PUBLISHED]);

        $this->get("/store/{$vendorA->slug}")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 1));
    }
}
