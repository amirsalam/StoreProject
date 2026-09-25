<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartTest extends TestCase
{
    use RefreshDatabase;

    public function test_cart_page_renders_empty_for_a_fresh_session(): void
    {
        $this->get('/cart')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('cart/index')
                ->has('items', 0)
                ->where('subtotal', 0)
            );
    }

    public function test_add_to_cart_persists_in_session(): void
    {
        $product = Product::factory()->digitalDownload()->create([
            'status' => Product::STATUS_PUBLISHED,
            'price' => 49.00,
            'sale_price' => null,
        ]);

        $this->post('/cart', ['product_id' => $product->id])
            ->assertRedirect()
            ->assertSessionHas('success');

        $summary = app(CartService::class)->summary();

        $this->assertSame(1, $summary['count']);
        $this->assertSame(49.0, $summary['subtotal']);
    }

    public function test_adding_same_product_increments_quantity(): void
    {
        $product = Product::factory()->digitalDownload()->create([
            'status' => Product::STATUS_PUBLISHED,
            'price' => 10.00,
            'sale_price' => null,
        ]);

        $this->post('/cart', ['product_id' => $product->id]);
        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);

        $summary = app(CartService::class)->summary();

        $this->assertSame(3, $summary['count']);
        $this->assertSame(30.0, $summary['subtotal']);
    }

    public function test_sale_price_is_used_when_present(): void
    {
        $product = Product::factory()->digitalDownload()->create([
            'status' => Product::STATUS_PUBLISHED,
            'price' => 100.00,
            'sale_price' => 60.00,
        ]);

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);

        $this->assertSame(120.0, app(CartService::class)->summary()['subtotal']);
    }

    public function test_subscription_quantity_is_locked_to_one(): void
    {
        $product = Product::factory()->subscription()->create([
            'status' => Product::STATUS_PUBLISHED,
            'price' => 29.00,
        ]);

        $this->post('/cart', ['product_id' => $product->id, 'quantity' => 5]);

        $this->assertSame(1, app(CartService::class)->summary()['count']);
    }

    public function test_cannot_add_unpublished_product(): void
    {
        $product = Product::factory()->draft()->create();

        $this->post('/cart', ['product_id' => $product->id])->assertNotFound();
    }

    public function test_validates_product_id(): void
    {
        $this->post('/cart', [])->assertSessionHasErrors('product_id');
        $this->post('/cart', ['product_id' => 999_999])->assertSessionHasErrors('product_id');
    }

    public function test_update_changes_quantity(): void
    {
        $product = Product::factory()->digitalDownload()->create([
            'status' => Product::STATUS_PUBLISHED,
            'price' => 10.00,
        ]);

        $this->post('/cart', ['product_id' => $product->id]);
        $this->patch("/cart/items/{$product->id}", ['quantity' => 4]);

        $this->assertSame(4, app(CartService::class)->summary()['count']);
    }

    public function test_update_to_zero_removes_item(): void
    {
        $product = Product::factory()->digitalDownload()->create([
            'status' => Product::STATUS_PUBLISHED,
        ]);

        $this->post('/cart', ['product_id' => $product->id]);
        $this->patch("/cart/items/{$product->id}", ['quantity' => 0]);

        $this->assertSame(0, app(CartService::class)->summary()['count']);
    }

    public function test_delete_removes_item(): void
    {
        $product = Product::factory()->digitalDownload()->create([
            'status' => Product::STATUS_PUBLISHED,
        ]);

        $this->post('/cart', ['product_id' => $product->id]);
        $this->delete("/cart/items/{$product->id}")
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertSame(0, app(CartService::class)->summary()['count']);
    }

    public function test_clear_empties_cart(): void
    {
        $a = Product::factory()->digitalDownload()->create(['status' => Product::STATUS_PUBLISHED]);
        $b = Product::factory()->digitalDownload()->create(['status' => Product::STATUS_PUBLISHED]);

        $this->post('/cart', ['product_id' => $a->id]);
        $this->post('/cart', ['product_id' => $b->id]);

        $this->delete('/cart');

        $this->assertSame(0, app(CartService::class)->summary()['count']);
    }

    public function test_unpublished_products_are_dropped_from_line_items(): void
    {
        $product = Product::factory()->digitalDownload()->create(['status' => Product::STATUS_PUBLISHED]);

        $this->post('/cart', ['product_id' => $product->id]);

        // Simulate the product being archived after it was added
        $product->update(['status' => Product::STATUS_ARCHIVED]);

        $this->assertSame(0, app(CartService::class)->lineItems()->count());
    }

    public function test_cart_is_shared_via_inertia(): void
    {
        $product = Product::factory()->digitalDownload()->create([
            'status' => Product::STATUS_PUBLISHED,
            'price' => 12.34,
            'sale_price' => null, // factory puts ~25% of products on sale at a random price
        ]);

        $this->post('/cart', ['product_id' => $product->id]);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cart.count', 1)
                ->where('cart.subtotal', 12.34)
            );
    }
}
