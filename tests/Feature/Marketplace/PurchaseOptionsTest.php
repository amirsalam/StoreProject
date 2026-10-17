<?php

namespace Tests\Feature\Marketplace;

use App\Events\PaymentCompleted;
use App\Models\License;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Marketplace-style buying: Regular / Extended License, support extended
 * to 12 months, quantity (one license key per unit) and the product's
 * preview options.
 */
class PurchaseOptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_are_priced_and_kept_as_separate_cart_lines(): void
    {
        $product = $this->product();

        $this->post(route('cart.add'), ['product_id' => $product->id, 'quantity' => 2])->assertRedirect();
        $this->post(route('cart.add'), ['product_id' => $product->id, 'extended' => true, 'extended_support' => true]);

        $lines = app(CartService::class)->lineItems()->keyBy('key');

        $this->assertSame(2, $lines[(string) $product->id]['quantity']);
        $this->assertSame(59.0, $lines[(string) $product->id]['unit_price']);
        $this->assertSame(6, $lines[(string) $product->id]['support_months']);

        $extended = $lines[$product->id.'-x-s'];
        $this->assertSame(299.25, $extended['unit_price']); // 279 extended + 20.25 support
        $this->assertTrue($extended['extended']);
        $this->assertSame(12, $extended['support_months']);
        $this->assertSame(417.25, app(CartService::class)->subtotal());
    }

    public function test_options_a_product_does_not_offer_are_ignored(): void
    {
        $product = $this->product(['extended_price' => null, 'support_extension_price' => null]);

        $this->post(route('cart.add'), ['product_id' => $product->id, 'extended' => true, 'extended_support' => true]);

        $line = app(CartService::class)->lineItems()->first();
        $this->assertSame((string) $product->id, $line['key']);
        $this->assertSame(59.0, $line['unit_price']);
    }

    public function test_cart_lines_are_updated_and_removed_by_key(): void
    {
        $product = $this->product();
        $this->post(route('cart.add'), ['product_id' => $product->id, 'extended' => true]);
        $key = $product->id.'-x';

        $this->patch(route('cart.update', $key), ['quantity' => 5]);
        $this->assertSame(5, app(CartService::class)->lineItems()->first()['quantity']);

        $this->delete(route('cart.destroy', $key));
        $this->assertTrue(app(CartService::class)->lineItems()->isEmpty());
    }

    public function test_each_license_unit_gets_its_own_key_with_the_tier_bought(): void
    {
        $product = $this->product(['sales_count' => 0]);
        $buyer = User::factory()->create();
        $order = Order::factory()->create(['user_id' => $buyer->id, 'status' => Order::STATUS_PAID, 'paid_at' => now()]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'product_type' => $product->type,
            'quantity' => 3,
            'unit_price' => 279,
            'total_price' => 837,
            'metadata' => ['license' => License::TIER_EXTENDED, 'extended_support' => false, 'support_months' => 6],
        ]);
        $payment = Payment::factory()->create(['order_id' => $order->id, 'user_id' => $buyer->id]);

        PaymentCompleted::dispatch($payment, $order);
        PaymentCompleted::dispatch($payment, $order); // replay: no extra keys

        $licenses = License::query()->where('user_id', $buyer->id)->get();
        $this->assertCount(3, $licenses);
        $this->assertSame(3, $licenses->pluck('license_key')->unique()->count());
        $this->assertSame(['extended'], $licenses->pluck('tier')->unique()->values()->all());
        $this->assertSame(3, $product->fresh()->sales_count);

        $this->actingAs($buyer)->get(route('purchases.index'))
            ->assertInertia(fn ($page) => $page
                ->has('purchases.data.0.licenses', 3)
                ->where('purchases.data.0.licenses.0.tier', 'extended')
                ->where('purchases.data.0.quantity', 3)
                ->whereNot('purchases.data.0.support_until', null));
    }

    public function test_the_product_form_saves_the_options_and_screenshots(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.products.store'), [
            'title' => 'Stocky POS',
            'slug' => 'stocky-pos',
            'type' => Product::TYPE_LICENSE,
            'price' => '59',
            'currency' => 'USD',
            'default_activation_limit' => 1,
            'status' => Product::STATUS_PUBLISHED,
            'extended_price' => '279',
            'support_months' => '6',
            'support_extension_price' => '20.25',
            'live_preview_url' => 'https://demo.example.com',
            'screenshots' => "https://img.example.com/1.png\r\n\r\nhttps://img.example.com/2.png\n",
        ])->assertSessionHasNoErrors();

        $product = Product::query()->where('slug', 'stocky-pos')->firstOrFail();
        $this->assertSame('279.00', $product->extended_price);
        $this->assertSame('20.25', $product->support_extension_price);
        $this->assertSame(6, $product->support_months);
        $this->assertSame('https://demo.example.com', $product->live_preview_url);
        $this->assertSame(['https://img.example.com/1.png', 'https://img.example.com/2.png'], $product->gallery);

        $this->get(route('products.show', $product->slug))
            ->assertInertia(fn ($page) => $page
                ->where('product.extended_price', '279.00')
                ->where('product.live_preview_url', 'https://demo.example.com')
                ->has('product.gallery', 2));
    }

    private function product(array $overrides = []): Product
    {
        return Product::factory()->license()->create([
            'status' => Product::STATUS_PUBLISHED,
            'price' => 59,
            'sale_price' => null,
            'extended_price' => 279,
            'support_months' => 6,
            'support_extension_price' => 20.25,
            ...$overrides,
        ]);
    }
}
