<?php

namespace Tests\Feature\Marketplace;

use App\Domain\Billing\StripeGateway;
use App\Events\PaymentCompleted;
use App\Models\Coupon;
use App\Models\Download;
use App\Models\License;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * Marketplace checkout: cart -> order + pending payment + Stripe intent,
 * and digital fulfillment fanning out from PaymentCompleted.
 *
 * Stripe is faked via the container (the same seam the gateway exists
 * for) so no live API call happens.
 */
class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function fakeStripe(): void
    {
        $this->app->instance(StripeGateway::class, new class extends StripeGateway
        {
            public function createPaymentIntent(int $amountCents, string $currency, array $metadata = []): array
            {
                return [
                    'id' => 'pi_fake_'.$amountCents,
                    'client_secret' => 'pi_fake_secret_'.$amountCents,
                    'customer' => null,
                ];
            }
        });
    }

    public function test_guest_cannot_access_checkout(): void
    {
        $this->get('/checkout')->assertRedirect('/login');
    }

    public function test_checkout_redirects_to_cart_when_empty(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get('/checkout')->assertRedirect(route('cart.show'));
    }

    public function test_placing_an_order_builds_it_from_server_side_prices_and_clears_cart(): void
    {
        $this->fakeStripe();
        $user = User::factory()->create();
        $product = Product::factory()->digitalDownload()->create([
            'status' => Product::STATUS_PUBLISHED,
            'price' => 49.00,
            'sale_price' => null,
        ]);

        $this->actingAs($user)->post('/cart', ['product_id' => $product->id, 'quantity' => 2]);

        $response = $this->actingAs($user)->post('/checkout', [
            'billing_name' => 'Ada Lovelace',
            'billing_email' => 'ada@example.test',
            // A malicious client cannot smuggle a price — the endpoint
            // ignores anything but billing + coupon.
            'unit_price' => 1,
        ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $response->assertRedirect(route('checkout.confirmation', $order->order_number));

        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame('98.00', (string) $order->subtotal);
        $this->assertSame('98.00', (string) $order->total);
        $this->assertCount(1, $order->items);
        $this->assertSame('49.00', (string) $order->items->first()->unit_price);
        $this->assertSame(2, $order->items->first()->quantity);

        $payment = Payment::query()->where('order_id', $order->id)->firstOrFail();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame('pi_fake_9800', $payment->gateway_payment_id);

        // Cart emptied after a successful order.
        $this->actingAs($user)->get('/checkout')->assertRedirect(route('cart.show'));
    }

    public function test_coupon_applies_discount_and_increments_used_count(): void
    {
        $this->fakeStripe();
        $user = User::factory()->create();
        $product = Product::factory()->digitalDownload()->create([
            'status' => Product::STATUS_PUBLISHED,
            'price' => 100.00,
            'sale_price' => null,
        ]);
        $coupon = Coupon::factory()->create([
            'code' => 'SAVE10',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 10,
            'is_active' => true,
            'used_count' => 0,
            'max_uses' => null,
            'min_order_amount' => null,
            'max_uses_per_user' => null,
            'starts_at' => null,
            'expires_at' => null,
        ]);

        $this->actingAs($user)->post('/cart', ['product_id' => $product->id]);
        $this->actingAs($user)->post('/checkout', [
            'billing_name' => 'Grace Hopper',
            'billing_email' => 'grace@example.test',
            'coupon_code' => 'SAVE10',
        ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('100.00', (string) $order->subtotal);
        $this->assertSame('10.00', (string) $order->discount);
        $this->assertSame('90.00', (string) $order->total);
        $this->assertSame($coupon->id, $order->coupon_id);

        $this->assertSame(1, $coupon->fresh()->used_count);
    }

    public function test_invalid_coupon_is_rejected_and_creates_no_order(): void
    {
        $this->fakeStripe();
        $user = User::factory()->create();
        $product = Product::factory()->digitalDownload()->create([
            'status' => Product::STATUS_PUBLISHED,
            'price' => 20.00,
            'sale_price' => null,
        ]);

        $this->actingAs($user)->post('/cart', ['product_id' => $product->id]);
        $this->actingAs($user)
            ->post('/checkout', [
                'billing_name' => 'Alan Turing',
                'billing_email' => 'alan@example.test',
                'coupon_code' => 'NOPE',
            ])
            ->assertSessionHasErrors('coupon_code');

        $this->assertSame(0, Order::query()->count());
    }

    public function test_payment_completed_fulfills_license_and_download_idempotently(): void
    {
        $user = User::factory()->create();
        $licenseProduct = Product::factory()->license()->create([
            'default_activation_limit' => 3,
        ]);
        $downloadProduct = Product::factory()->digitalDownload()->create([
            'download_limit' => 5,
        ]);

        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PAID,
        ]);
        $licenseItem = $order->items()->create([
            'product_id' => $licenseProduct->id,
            'product_title' => $licenseProduct->title,
            'product_type' => $licenseProduct->type,
            'quantity' => 1,
            'unit_price' => $licenseProduct->price,
            'total_price' => $licenseProduct->price,
        ]);
        $downloadItem = $order->items()->create([
            'product_id' => $downloadProduct->id,
            'product_title' => $downloadProduct->title,
            'product_type' => $downloadProduct->type,
            'quantity' => 1,
            'unit_price' => $downloadProduct->price,
            'total_price' => $downloadProduct->price,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => Payment::STATUS_SUCCEEDED,
        ]);

        // Fire twice — fulfillment must be idempotent.
        PaymentCompleted::dispatch($payment, $order);
        PaymentCompleted::dispatch($payment->fresh(), $order->fresh());

        $this->assertSame(1, License::query()->where('order_item_id', $licenseItem->id)->count());
        $this->assertSame(1, Download::query()->where('order_item_id', $downloadItem->id)->count());

        $license = License::query()->where('order_item_id', $licenseItem->id)->firstOrFail();
        $this->assertSame(3, $license->activation_limit);
        $this->assertSame(License::STATUS_ACTIVE, $license->status);

        $download = Download::query()->where('order_item_id', $downloadItem->id)->firstOrFail();
        $this->assertSame(5, $download->max_downloads);
    }

    public function test_fully_discounted_order_is_settled_and_fulfilled_without_stripe(): void
    {
        // No fakeStripe(): a $0 order must never call the gateway.
        $user = User::factory()->create();
        $product = Product::factory()->license()->create([
            'status' => Product::STATUS_PUBLISHED,
            'price' => 50.00,
            'sale_price' => null,
            'default_activation_limit' => 1,
        ]);
        $coupon = Coupon::factory()->create([
            'code' => 'FREE100',
            'type' => Coupon::TYPE_PERCENTAGE,
            'value' => 100,
            'is_active' => true,
            'used_count' => 0,
            'max_uses' => null,
            'min_order_amount' => null,
            'max_uses_per_user' => null,
            'starts_at' => null,
            'expires_at' => null,
        ]);

        $this->actingAs($user)->post('/cart', ['product_id' => $product->id]);
        $this->actingAs($user)->post('/checkout', [
            'billing_name' => 'Free Buyer',
            'billing_email' => 'free@example.test',
            'coupon_code' => 'FREE100',
        ]);

        $order = Order::query()->where('user_id', $user->id)->firstOrFail();
        $this->assertSame('0.00', (string) $order->total);
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertNotNull($order->paid_at);

        // Fulfillment ran from the $0 settlement path.
        $this->assertSame(1, License::query()->where('user_id', $user->id)->count());
    }

    public function test_listener_is_registered_for_payment_completed(): void
    {
        $listeners = Event::getListeners(PaymentCompleted::class);
        $this->assertNotEmpty($listeners);
    }
}
