<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\StripeCredentials;
use App\Jobs\ProcessPaymentWebhook;
use App\Models\License;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Stripe keys come only from the store's gateway in Admin → Payment
 * Gateways (never .env), and the webhook routes one-time payment events
 * to the order processor — so a card payment actually marks the order
 * paid and fulfils it.
 */
class StripeCheckoutWiringTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tenancy.central_domain' => 'example.test',
            'tenancy.central_fallback_tenant' => null,
            'services.stripe.key' => null,
            'services.stripe.secret' => null,
            'services.stripe.webhook_secret' => null,
        ]);

        $this->tenant = Tenant::factory()->create();
    }

    public function test_keys_come_from_the_store_gateway_in_the_dashboard(): void
    {
        $this->gateway(['credentials' => ['publishable_key' => 'pk_test_store', 'secret_key' => 'sk_test_store']]);

        app(TenantContext::class)->set($this->tenant);

        $this->assertSame(
            ['publishable_key' => 'pk_test_store', 'secret_key' => 'sk_test_store'],
            app(StripeCredentials::class)->keys(),
        );
    }

    public function test_env_keys_are_never_used_for_checkout(): void
    {
        config(['services.stripe.key' => 'pk_test_env', 'services.stripe.secret' => 'sk_test_env']);

        app(TenantContext::class)->set($this->tenant);

        $this->assertSame(['publishable_key' => null, 'secret_key' => null], app(StripeCredentials::class)->keys());
    }

    public function test_an_inactive_gateway_provides_no_keys(): void
    {
        $this->gateway(['is_active' => false, 'credentials' => ['publishable_key' => 'pk_test_off', 'secret_key' => 'sk_test_off']]);

        app(TenantContext::class)->set($this->tenant);

        $this->assertSame(['publishable_key' => null, 'secret_key' => null], app(StripeCredentials::class)->keys());
    }

    public function test_a_half_configured_gateway_provides_no_keys(): void
    {
        $this->gateway(['credentials' => ['publishable_key' => 'pk_test_half', 'secret_key' => '']]);

        app(TenantContext::class)->set($this->tenant);

        $this->assertSame(['publishable_key' => null, 'secret_key' => null], app(StripeCredentials::class)->keys());
    }

    public function test_another_stores_gateway_is_never_used(): void
    {
        $this->gateway(['tenant_id' => Tenant::factory()->create()->id]);

        app(TenantContext::class)->set($this->tenant);
        $this->assertSame(['publishable_key' => null, 'secret_key' => null], app(StripeCredentials::class)->keys());

        // No tenant in context: the store gateways are out of reach entirely.
        app(TenantContext::class)->set(null);
        $this->assertNull(app(StripeCredentials::class)->gateway());
    }

    public function test_checkout_page_uses_the_store_gateway_publishable_key(): void
    {
        $this->gateway(['credentials' => ['publishable_key' => 'pk_test_store', 'secret_key' => 'sk_test_store']]);
        $user = User::factory()->create();
        $product = Product::factory()->digitalDownload()->create(['tenant_id' => $this->tenant->id, 'status' => Product::STATUS_PUBLISHED, 'price' => 10, 'sale_price' => null]);

        $this->actingAs($user)->post($this->url('/cart'), ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)->get($this->url('/checkout'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('checkout/index')
                ->where('stripeKey', 'pk_test_store')
            );
    }

    public function test_checkout_page_reports_payments_unavailable_without_any_keys(): void
    {
        $user = User::factory()->create();
        $product = Product::factory()->digitalDownload()->create(['tenant_id' => $this->tenant->id, 'status' => Product::STATUS_PUBLISHED, 'price' => 10, 'sale_price' => null]);

        $this->actingAs($user)->post($this->url('/cart'), ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAs($user)->get($this->url('/checkout'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('stripeKey', null));
    }

    public function test_signed_payment_webhook_marks_the_order_paid_and_fulfils_it(): void
    {
        $this->gateway(['webhook_secret' => 'whsec_store']);
        $payment = $this->pendingLicensePayment('pi_live_flow');

        $payload = $this->event('evt_paid_1', 'payment_intent.succeeded', ['id' => 'pi_live_flow', 'object' => 'payment_intent']);

        $this->postSigned($payload, 'whsec_store')->assertOk();

        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->refresh()->status);
        $this->assertSame(Order::STATUS_PAID, $payment->order->refresh()->status);
        $this->assertSame(1, License::query()->forTenant($this->tenant)->where('order_item_id', $payment->order->items->first()->id)->count());
    }

    public function test_payment_events_are_queued_and_billing_events_are_not(): void
    {
        Queue::fake();
        config(['services.stripe.webhook_secret' => 'whsec_platform']);

        $this->postSigned($this->event('evt_pay', 'charge.refunded', ['id' => 'ch_1', 'object' => 'charge']), 'whsec_platform')->assertOk();
        Queue::assertPushed(ProcessPaymentWebhook::class, fn (ProcessPaymentWebhook $job) => $job->eventId === 'evt_pay' && $job->eventType === 'charge.refunded');

        $this->postSigned($this->event('evt_bill', 'invoice.paid', ['id' => 'in_1', 'object' => 'invoice']), 'whsec_platform')->assertOk();
        Queue::assertPushed(ProcessPaymentWebhook::class, 1);
        $this->assertTrue(WebhookEvent::query()->where('gateway_event_id', 'evt_bill')->exists(), 'billing events still reach the billing processor');
    }

    public function test_a_signature_from_an_unknown_secret_is_rejected(): void
    {
        $this->gateway(['webhook_secret' => 'whsec_store']);

        $this->postSigned($this->event('evt_x', 'payment_intent.succeeded', ['id' => 'pi_x']), 'whsec_attacker')
            ->assertStatus(400);
    }

    public function test_webhook_without_any_secret_is_refused(): void
    {
        $this->postSigned($this->event('evt_y', 'invoice.paid', ['id' => 'in_y']), 'whsec_any')
            ->assertStatus(503);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function gateway(array $attributes = []): PaymentGateway
    {
        return PaymentGateway::factory()->create(['tenant_id' => $this->tenant->id, ...$attributes]);
    }

    private function url(string $path): string
    {
        return "http://{$this->tenant->slug}.example.test{$path}";
    }

    private function pendingLicensePayment(string $intentId): Payment
    {
        $user = User::factory()->create();
        $product = Product::factory()->license()->create(['tenant_id' => $this->tenant->id, 'default_activation_limit' => 1]);
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING,
            'total' => 25.00,
            'currency' => 'USD',
        ]);
        $order->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'product_title' => $product->title,
            'product_type' => $product->type,
            'quantity' => 1,
            'unit_price' => 25.00,
            'total_price' => 25.00,
        ]);

        return Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => 25.00,
            'currency' => 'USD',
            'gateway' => 'stripe',
            'gateway_payment_id' => $intentId,
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    /**
     * @param  array<string, mixed>  $object
     */
    private function event(string $id, string $type, array $object): string
    {
        return (string) json_encode([
            'id' => $id,
            'object' => 'event',
            'type' => $type,
            'data' => ['object' => $object],
        ]);
    }

    /**
     * Post with a genuine Stripe-Signature header (HMAC-SHA256 of "t.payload").
     */
    private function postSigned(string $payload, string $secret): TestResponse
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

        return $this->call('POST', $this->url('/webhooks/stripe'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => "t={$timestamp},v1={$signature}",
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }
}
