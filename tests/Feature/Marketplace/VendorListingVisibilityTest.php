<?php

namespace Tests\Feature\Marketplace;

use App\Models\Product;
use App\Models\Vendor;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * A vendor's catalog is only listed while the vendor is ACTIVE
 * (marketplace doc §11: reviewed before listing; suspension hides
 * products). Operator-owned products (no vendor) are unaffected.
 */
class VendorListingVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function productFor(?Vendor $vendor): Product
    {
        return Product::factory()->digitalDownload()->create([
            'vendor_id' => $vendor?->id,
            'status' => Product::STATUS_PUBLISHED,
            'sale_price' => null,
        ]);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function hiddenStatuses(): array
    {
        return [
            'pending' => [Vendor::STATUS_PENDING],
            'suspended' => [Vendor::STATUS_SUSPENDED],
            'rejected' => [Vendor::STATUS_REJECTED],
        ];
    }

    public function test_operator_and_active_vendor_products_are_listed(): void
    {
        $operator = $this->productFor(null);
        $vendored = $this->productFor(Vendor::factory()->create());

        $this->get('/products')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('products.data', 2));

        $this->get("/products/{$operator->slug}")->assertOk();
        $this->get("/products/{$vendored->slug}")->assertOk();
        $this->post('/cart', ['product_id' => $vendored->id])->assertSessionHasNoErrors();
    }

    #[DataProvider('hiddenStatuses')]
    public function test_products_of_a_non_active_vendor_are_unlisted(string $status): void
    {
        $this->productFor(null);
        $hidden = $this->productFor(Vendor::factory()->create(['status' => $status]));

        $this->get('/products')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('products.data', 1)
                ->where('products.data.0.id', fn ($id) => $id !== $hidden->id)
            );

        $this->get("/products/{$hidden->slug}")->assertNotFound();
        $this->post('/cart', ['product_id' => $hidden->id])->assertNotFound();
        $this->assertSame(0, app(CartService::class)->summary()['count']);
    }

    public function test_a_soft_deleted_vendors_products_are_unlisted(): void
    {
        $vendor = Vendor::factory()->create();
        $product = $this->productFor($vendor);
        $vendor->delete();

        $this->get("/products/{$product->slug}")->assertNotFound();
    }

    public function test_suspending_a_vendor_drops_their_product_from_an_open_cart(): void
    {
        $vendor = Vendor::factory()->create();
        $product = $this->productFor($vendor);

        $this->post('/cart', ['product_id' => $product->id]);
        $this->assertSame(1, app(CartService::class)->summary()['count']);

        // Checkout prices from these line items, so it can't be bought either.
        $vendor->forceFill(['status' => Vendor::STATUS_SUSPENDED])->save();

        $this->assertSame(0, app(CartService::class)->summary()['count']);
    }

    public function test_related_products_skip_unlisted_vendors(): void
    {
        $shown = $this->productFor(null);
        $related = $this->productFor(null);
        $hidden = $this->productFor(Vendor::factory()->pending()->create());
        $hidden->update(['category_id' => $shown->category_id]);
        $related->update(['category_id' => $shown->category_id]);

        $this->get("/products/{$shown->slug}")
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('relatedProducts', 1)
                ->where('relatedProducts.0.id', $related->id)
            );
    }
}
