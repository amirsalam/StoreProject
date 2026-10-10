<?php

namespace Tests\Feature\Payments;

use App\Domain\Billing\StripeGateway;
use App\Models\License;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * PayPal Checkout, end to end against a faked PayPal REST API:
 * checkout → PayPal order + approval link → buyer returns → capture
 * verified (status, amount, currency, our order number) → order paid,
 * fulfilled, emailed. Stripe is never called.
 */
class PayPalCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $buyer;

    /** What the faked PayPal capture reports. */
    private array $capture = ['status' => 'COMPLETED', 'value' => null, 'currency' => 'USD', 'reference' => null];

    protected function setUp(): void
    {
        parent::setUp();

        config(['tenancy.central_domain' => 'example.test', 'tenancy.central_fallback_tenant' => null]);
        $this->tenant = Tenant::factory()->create();
        $this->buyer = User::factory()->create();

        $this->app->instance(StripeGateway::class, new class extends StripeGateway
        {
            public function createPaymentIntent(int $amountCents, string $currency, array $metadata = []): array
            {
                throw new \LogicException('Stripe must not be called for a PayPal payment.');
            }
        });

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'A21-test-token', 'expires_in' => 32400]),
            '*/v2/checkout/orders' => fn (Request $request) => Http::response([
                'id' => 'PAYPAL-ORDER-1',
                'status' => 'PAYER_ACTION_REQUIRED',
                'links' => [['rel' => 'payer-action', 'href' => 'https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-1']],
            ], 201),
            '*/v2/checkout/orders/PAYPAL-ORDER-1/capture' => function () {
                $order = Order::query()->forTenant($this->tenant)->latest('id')->first();

                return Http::response([
                    'id' => 'PAYPAL-ORDER-1',
                    'status' => $this->capture['status'],
                    'payer' => ['email_address' => 'buyer@example.test'],
                    'purchase_units' => [[
                        'reference_id' => $this->capture['reference'] ?? $order->order_number,
                        'payments' => ['captures' => [[
                            'id' => 'CAPTURE-1',
                            'status' => $this->capture['status'],
                            'amount' => ['currency_code' => $this->capture['currency'], 'value' => $this->capture['value'] ?? number_format((float) $order->total, 2, '.', '')],
                        ]]],
                    ]],
                ], 201);
            },
        ]);
    }

    public function test_checkout_offers_paypal_when_it_is_active(): void
    {
        $this->addToCart(25.00);

        $this->actingAs($this->buyer)->get($this->url('/checkout'))
            ->assertInertia(fn (Assert $page) => $page->where('paypal', false));

        $this->paypalGateway();

        $this->actingAs($this->buyer)->get($this->url('/checkout'))
            ->assertInertia(fn (Assert $page) => $page->where('paypal', true));
    }

    public function test_placing_an_order_creates_a_paypal_order_for_the_exact_total(): void
    {
        $this->paypalGateway();
        $this->addToCart(25.00);

        $response = $this->actingAs($this->buyer)->postJson($this->url('/checkout'), $this->billing())->assertOk();
        $order = Order::query()->forTenant($this->tenant)->sole();
        $payment = $order->payments()->sole();

        $this->assertSame('paypal', $order->payment_method);
        $this->assertSame('paypal', $payment->gateway);
        $this->assertSame('PAYPAL-ORDER-1', $payment->gateway_payment_id);
        $this->assertSame('https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-1', $response->json('redirect_url'));

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v2/checkout/orders')
            && str_starts_with($r->url(), 'https://api-m.sandbox.paypal.com')
            && $r['purchase_units'][0]['amount'] === ['currency_code' => 'USD', 'value' => '25.00']
            && $r['purchase_units'][0]['reference_id'] === $order->order_number
            && str_contains($r['payment_source']['paypal']['experience_context']['return_url'], "/checkout/{$order->order_number}/paypal/return"));
    }

    public function test_returning_from_paypal_captures_marks_paid_fulfils_and_emails(): void
    {
        Notification::fake();
        $order = $this->placedOrder();

        $this->actingAs($this->buyer)
            ->get($this->url("/checkout/{$order->order_number}/paypal/return?token=PAYPAL-ORDER-1&PayerID=PAYER1"))
            ->assertRedirect($this->url("/checkout/{$order->order_number}/confirmation"));

        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame(Payment::STATUS_SUCCEEDED, $order->payments()->sole()->status);
        $this->assertSame(1, License::query()->forTenant($this->tenant)->where('order_item_id', $order->items()->first()->id)->count());
        Notification::assertSentOnDemandTimes(OrderConfirmation::class, 1);

        // Reloading the return page doesn't pay or email twice.
        $this->actingAs($this->buyer)->get($this->url("/checkout/{$order->order_number}/paypal/return?token=PAYPAL-ORDER-1"));
        Notification::assertSentOnDemandTimes(OrderConfirmation::class, 1);
    }

    public function test_a_capture_for_a_different_amount_is_refused(): void
    {
        $order = $this->placedOrder();
        $this->capture['value'] = '1.00';

        $this->actingAs($this->buyer)
            ->get($this->url("/checkout/{$order->order_number}/paypal/return?token=PAYPAL-ORDER-1"))
            ->assertRedirect($this->url("/checkout/{$order->order_number}/confirmation?payment=failed"));

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_a_pending_capture_is_not_treated_as_paid(): void
    {
        $order = $this->placedOrder();
        $this->capture['status'] = 'PENDING';

        $this->actingAs($this->buyer)->get($this->url("/checkout/{$order->order_number}/paypal/return?token=PAYPAL-ORDER-1"));

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_a_token_for_another_paypal_order_is_refused(): void
    {
        $order = $this->placedOrder();

        $this->actingAs($this->buyer)
            ->get($this->url("/checkout/{$order->order_number}/paypal/return?token=SOMEONE-ELSES"))
            ->assertRedirect($this->url("/checkout/{$order->order_number}/confirmation?payment=failed"));

        Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/capture'));
        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_only_the_buyer_can_complete_their_order(): void
    {
        $order = $this->placedOrder();

        $this->actingAs(User::factory()->create())
            ->get($this->url("/checkout/{$order->order_number}/paypal/return?token=PAYPAL-ORDER-1"))
            ->assertNotFound();

        $this->assertSame(Order::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_cancelling_on_paypal_offers_to_try_again(): void
    {
        $order = $this->placedOrder();

        $this->actingAs($this->buyer)
            ->get($this->url("/checkout/{$order->order_number}/paypal/cancel?token=PAYPAL-ORDER-1"))
            ->assertRedirect($this->url("/checkout/{$order->order_number}/confirmation?payment=failed"));

        $this->actingAs($this->buyer)->get($this->url("/checkout/{$order->order_number}/confirmation?payment=failed"))
            ->assertInertia(fn (Assert $page) => $page
                ->where('paymentFailed', true)
                ->where('retryUrl', $this->url("/checkout/{$order->order_number}/paypal")));

        $this->actingAs($this->buyer)->get($this->url("/checkout/{$order->order_number}/paypal"))
            ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-1');
    }

    public function test_an_order_started_with_card_can_switch_to_paypal(): void
    {
        Notification::fake();
        $this->paypalGateway();
        $product = Product::factory()->license()->create(['tenant_id' => $this->tenant->id, 'status' => Product::STATUS_PUBLISHED]);
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->buyer->id,
            'status' => Order::STATUS_PENDING,
            'payment_method' => 'stripe',
            'total' => 30.00,
            'currency' => 'USD',
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_title' => $product->title, 'product_type' => $product->type,
            'quantity' => 1, 'unit_price' => 30, 'total_price' => 30,
        ]);
        Payment::factory()->create(['order_id' => $order->id, 'user_id' => $this->buyer->id, 'gateway' => 'stripe', 'status' => Payment::STATUS_PENDING, 'gateway_payment_id' => 'pi_unused']);

        $this->actingAs($this->buyer)->get($this->url("/checkout/{$order->order_number}/paypal"))
            ->assertRedirect('https://www.sandbox.paypal.com/checkoutnow?token=PAYPAL-ORDER-1');

        $order->refresh();
        $this->assertSame('paypal', $order->payment_method);
        $this->assertSame('PAYPAL-ORDER-1', $order->payments()->where('gateway', 'paypal')->sole()->gateway_payment_id);

        $this->actingAs($this->buyer)->get($this->url("/checkout/{$order->order_number}/paypal/return?token=PAYPAL-ORDER-1"));
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
        Notification::assertSentOnDemandTimes(OrderConfirmation::class, 1);
    }

    public function test_test_connection_checks_the_credentials_with_paypal(): void
    {
        $gateway = $this->paypalGateway();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post($this->url("/admin/payment-gateways/{$gateway->id}/test"))->assertSessionHas('success');
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/v1/oauth2/token'));
    }

    private function paypalGateway(): PaymentGateway
    {
        return PaymentGateway::factory()->create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'paypal',
            'name' => 'paypal-gateway',
            'display_name' => 'PayPal',
            'environment' => PaymentGateway::ENV_SANDBOX,
            'is_active' => true,
            'credentials' => ['client_id' => 'sandbox-client-id', 'client_secret' => 'sandbox-secret'],
        ]);
    }

    private function placedOrder(): Order
    {
        $this->paypalGateway();
        $this->addToCart(25.00, license: true);
        $this->actingAs($this->buyer)->postJson($this->url('/checkout'), $this->billing())->assertOk();

        return Order::query()->forTenant($this->tenant)->sole();
    }

    private function addToCart(float $price, bool $license = false): void
    {
        $factory = $license ? Product::factory()->license() : Product::factory()->digitalDownload();
        $product = $factory->create(['tenant_id' => $this->tenant->id, 'status' => Product::STATUS_PUBLISHED, 'price' => $price, 'sale_price' => null]);

        $this->actingAs($this->buyer)->post($this->url('/cart'), ['product_id' => $product->id, 'quantity' => 1]);
    }

    /**
     * @return array<string, string>
     */
    private function billing(): array
    {
        return ['billing_name' => 'Ada Lovelace', 'billing_email' => 'ada@example.test', 'payment_method' => 'paypal'];
    }

    private function url(string $path): string
    {
        return "http://{$this->tenant->slug}.example.test{$path}";
    }
}
