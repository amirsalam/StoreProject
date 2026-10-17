<?php

namespace Tests\Feature\Marketplace;

use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Services\LicensingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Admin → Licensing: a store-wide Extended License at regular price × N,
 * for every licensable product without its own Extended price.
 */
class ExtendedLicenseDefaultTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_turns_on_the_store_wide_extended_license(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->put(route('admin.licensing.update'), ['extended_enabled' => true, 'extended_multiplier' => '5'])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(['extended_enabled' => true, 'extended_multiplier' => 5.0], app(LicensingSettings::class)->forForm());

        $this->actingAs($admin)->get(route('admin.licensing.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('settings.extended_enabled', true));
    }

    public function test_only_admins_change_licensing(): void
    {
        $this->actingAs(User::factory()->create())
            ->put(route('admin.licensing.update'), ['extended_enabled' => true, 'extended_multiplier' => '5'])
            ->assertForbidden();
    }

    public function test_products_without_their_own_price_get_the_default(): void
    {
        $this->enable(5);
        $product = Product::factory()->license()->create(['status' => Product::STATUS_PUBLISHED, 'price' => 59, 'sale_price' => null, 'extended_price' => null]);

        $this->assertSame('295.00', $product->effectiveExtendedPrice());

        $this->get(route('products.show', $product->slug))
            ->assertInertia(fn (Assert $page) => $page->where('product.effective_extended_price', '295.00'));

        $this->post(route('cart.add'), ['product_id' => $product->id, 'extended' => true]);
        $this->assertSame(295.0, app(CartService::class)->lineItems()->first()['unit_price']);
    }

    public function test_a_products_own_extended_price_wins(): void
    {
        $this->enable(5);
        $product = Product::factory()->license()->create(['price' => 59, 'extended_price' => 159]);

        $this->assertSame('159.00', $product->effectiveExtendedPrice());
    }

    public function test_subscriptions_and_a_disabled_rule_offer_no_extended_license(): void
    {
        $subscription = Product::factory()->subscription()->create(['price' => 20, 'extended_price' => null]);
        $license = Product::factory()->license()->create(['price' => 59, 'extended_price' => null]);

        $this->assertNull($license->effectiveExtendedPrice()); // rule off

        $this->enable(5);
        $this->assertNull($subscription->fresh()->effectiveExtendedPrice());
        $this->assertFalse($subscription->fresh()->offersExtendedLicense());
    }

    private function enable(float $multiplier): void
    {
        app(LicensingSettings::class)->save(['extended_enabled' => true, 'extended_multiplier' => $multiplier]);
    }
}
