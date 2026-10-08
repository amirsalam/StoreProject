<?php

namespace Tests\Feature\Payments;

use App\Domain\Billing\StripeGateway;
use App\Domain\Payments\Cmi\CmiGateway;
use App\Models\License;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\OrderConfirmation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * CMI (Morocco) hosted payment, end to end with signed CMI messages:
 * checkout → signed form to CMI → callback / browser return verified with
 * the Store Key → order paid, fulfilled, emailed. Stripe is untouched.
 */
class CmiCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const STORE_KEY = 'TEST_STORE_KEY_123';

    private Tenant $tenant;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        config(['tenancy.central_domain' => 'example.test', 'tenancy.central_fallback_tenant' => null]);
        $this->tenant = Tenant::factory()->create();
        $this->buyer = User::factory()->create();

        // Checkout must never touch Stripe on the CMI path.
        $this->app->instance(StripeGateway::class, new class extends StripeGateway
        {
            public function createPaymentIntent(int $amountCents, string $currency, array $metadata = []): array
            {
                throw new \LogicException('Stripe must not be called for a CMI payment.');
            }
        });
    }

    public function test_checkout_offers_cmi_with_the_dirham_amount(): void
    {
        $this->cmiGateway();
        $this->addToCart(25.00);

        $this->actingAs($this->buyer)->get($this->url('/checkout'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('stripeKey', null)
                ->where('cmi.amount_mad', '250.00')
                ->where('cmi.rate', 10)
            );
    }

    public function test_a_dirham_cart_is_charged_by_cmi_as_is(): void
    {
        $this->cmiGateway();
        $this->addToCart(46.16, currency: 'MAD');

        $this->actingAs($this->buyer)->get($this->url('/checkout'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('cmi.amount_mad', '46.16')
                ->where('cmi.rate', null));

        $response = $this->actingAs($this->buyer)->postJson($this->url('/checkout'), $this->billing())->assertOk();
        $order = Order::query()->forTenant($this->tenant)->sole();

        $this->assertSame('MAD', $order->currency);
        $this->assertSame('46.16', $order->payments()->sole()->raw_response['cmi']['amount_mad']);

        $html = $this->actingAs($this->buyer)->get($response->json('redirect_url'))->assertOk()->getContent();
        preg_match_all('/name="([^"]+)" value="([^"]*)"/', $html, $m);
        $fields = array_combine($m[1], array_map('html_entity_decode', $m[2]));
        $this->assertSame('46.16', $fields['amount']);
        $this->assertSame('504', $fields['currency']);
    }

    public function test_cmi_is_not_offered_for_currencies_other_than_dirham_and_dollar(): void
    {
        $this->cmiGateway();
        $this->addToCart(20.00, currency: 'EUR');

        $this->actingAs($this->buyer)->get($this->url('/checkout'))
            ->assertInertia(fn (Assert $page) => $page->where('cmi', null));

        $this->actingAs($this->buyer)->postJson($this->url('/checkout'), $this->billing())->assertStatus(503);
    }

    public function test_placing_a_cmi_order_sends_the_buyer_to_a_signed_cmi_form(): void
    {
        $this->cmiGateway();
        $this->addToCart(25.00);

        $response = $this->actingAs($this->buyer)->postJson($this->url('/checkout'), $this->billing())->assertOk();
        $order = Order::query()->forTenant($this->tenant)->sole();
        $payment = $order->payments()->sole();

        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame('cmi', $payment->gateway);
        $this->assertSame($order->order_number, $payment->gateway_payment_id);
        $this->assertSame('250.00', $payment->raw_response['cmi']['amount_mad']);
        $this->assertSame($this->url("/checkout/{$order->order_number}/cmi"), $response->json('redirect_url'));

        $page = $this->actingAs($this->buyer)->get($this->url("/checkout/{$order->order_number}/cmi"))->assertOk();
        $html = $page->getContent();

        $this->assertStringContainsString('action="'.CmiGateway::ENDPOINTS[PaymentGateway::ENV_SANDBOX].'"', $html);
        preg_match_all('/name="([^"]+)" value="([^"]*)"/', $html, $m);
        $fields = array_combine($m[1], array_map('html_entity_decode', $m[2]));

        $this->assertSame('600001234', $fields['clientid']);
        $this->assertSame('250.00', $fields['amount']);
        $this->assertSame('504', $fields['currency']);
        $this->assertSame($order->order_number, $fields['oid']);
        $this->assertSame($fields['HASH'], CmiGateway::hash($fields, self::STORE_KEY), 'the form must be signed with the Store Key');
    }

    public function test_an_approved_callback_marks_the_order_paid_fulfils_and_emails_it(): void
    {
        Notification::fake();
        $order = $this->placedCmiOrder();

        $this->post($this->url('/payments/cmi/callback'), $this->signed($order, ['ProcReturnCode' => '00', 'Response' => 'Approved']))
            ->assertOk()
            ->assertSee('ACTION=POSTAUTH', false);

        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame(Payment::STATUS_SUCCEEDED, $order->payments()->sole()->status);
        $this->assertSame(1, License::query()->forTenant($this->tenant)->where('order_item_id', $order->items()->first()->id)->count());
        Notification::assertSentOnDemandTimes(OrderConfirmation::class, 1);

        // CMI retries callbacks, and the browser return may follow: no double processing.
        $this->post($this->url('/payments/cmi/callback'), $this->signed($order, ['ProcReturnCode' => '00', 'Response' => 'Approved']))
            ->assertSee('ACTION=POSTAUTH', false);
        Notification::assertSentOnDemandTimes(OrderConfirmation::class, 1);
    }

    public function test_a_callback_with_a_bad_hash_is_refused(): void
    {
        $order = $this->placedCmiOrder();
        $params = $this->signed($order, ['ProcReturnCode' => '00']);
        $params['HASH'] = base64_encode(str_repeat('x', 64));

        $this->post($this->url('/payments/cmi/callback'), $params)->assertSee('FAILURE', false);

        $this->assertSame(Order::STATUS_PENDING, $order->refresh()->status);
    }

    public function test_a_signed_callback_for_the_wrong_amount_is_refused(): void
    {
        $order = $this->placedCmiOrder();

        $this->post($this->url('/payments/cmi/callback'), $this->signed($order, ['ProcReturnCode' => '00', 'amount' => '1.00']))
            ->assertSee('FAILURE', false);

        $this->assertSame(Order::STATUS_PENDING, $order->refresh()->status);
    }

    public function test_a_declined_callback_is_acknowledged_and_the_order_stays_unpaid(): void
    {
        $order = $this->placedCmiOrder();

        $this->post($this->url('/payments/cmi/callback'), $this->signed($order, ['ProcReturnCode' => '05', 'ErrMsg' => 'Do not honour']))
            ->assertSee('APPROVED', false);

        $this->assertSame(Order::STATUS_PENDING, $order->refresh()->status);
        $this->assertSame('Do not honour', $order->payments()->sole()->failure_reason);
    }

    public function test_the_browser_return_confirms_payment_without_the_callback(): void
    {
        $order = $this->placedCmiOrder();

        // CMI posts cross-site: no session, so no auth needed here.
        $this->post($this->url("/checkout/{$order->order_number}/cmi/ok"), $this->signed($order, ['ProcReturnCode' => '00']))
            ->assertRedirect(route('checkout.confirmation', $order->order_number));

        $this->assertSame(Order::STATUS_PAID, $order->refresh()->status);
    }

    public function test_a_failed_payment_lands_on_the_order_with_a_retry(): void
    {
        $order = $this->placedCmiOrder();

        $this->post($this->url("/checkout/{$order->order_number}/cmi/fail"), $this->signed($order, ['ProcReturnCode' => '99']))
            ->assertRedirect(route('checkout.confirmation', ['order' => $order->order_number, 'payment' => 'failed']));

        $this->actingAs($this->buyer)
            ->get($this->url("/checkout/{$order->order_number}/confirmation?payment=failed"))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('paymentFailed', true)
                ->where('retryUrl', $this->url("/checkout/{$order->order_number}/cmi"))
            );
    }

    public function test_choosing_cmi_without_a_configured_gateway_is_refused(): void
    {
        $this->addToCart(25.00);

        $this->actingAs($this->buyer)->postJson($this->url('/checkout'), $this->billing())->assertStatus(503);

        $this->assertSame(0, Order::query()->forTenant($this->tenant)->count());
    }

    public function test_test_connection_checks_the_cmi_configuration(): void
    {
        $gateway = $this->cmiGateway();

        $this->actingAs(User::factory()->admin()->create())
            ->post($this->url("/admin/payment-gateways/{$gateway->id}/test"))
            ->assertSessionHas('success', fn (string $m) => str_starts_with($m, 'CMI is configured (test platform, 1 USD = 10 MAD)'));
    }

    private function cmiGateway(): PaymentGateway
    {
        return PaymentGateway::factory()->create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'cmi',
            'name' => 'CMI',
            'display_name' => 'CMI',
            'environment' => PaymentGateway::ENV_SANDBOX,
            'is_active' => true,
            'credentials' => ['client_id' => '600001234', 'store_key' => self::STORE_KEY, 'mad_rate' => '10'],
        ]);
    }

    private function placedCmiOrder(): Order
    {
        $this->cmiGateway();
        $this->addToCart(25.00, license: true);
        $this->actingAs($this->buyer)->postJson($this->url('/checkout'), $this->billing())->assertOk();

        return Order::query()->forTenant($this->tenant)->sole();
    }

    private function addToCart(float $price, bool $license = false, string $currency = 'USD'): void
    {
        $factory = $license ? Product::factory()->license() : Product::factory()->digitalDownload();
        $product = $factory->create(['tenant_id' => $this->tenant->id, 'status' => Product::STATUS_PUBLISHED, 'price' => $price, 'sale_price' => null, 'currency' => $currency]);

        $this->actingAs($this->buyer)->post($this->url('/cart'), ['product_id' => $product->id, 'quantity' => 1]);
    }

    /**
     * @return array<string, string>
     */
    private function billing(): array
    {
        return ['billing_name' => 'Ada Lovelace', 'billing_email' => 'ada@example.test', 'payment_method' => 'cmi'];
    }

    /**
     * A CMI result message, signed like CMI does.
     *
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function signed(Order $order, array $overrides): array
    {
        $params = [
            'clientid' => '600001234',
            'oid' => $order->order_number,
            'amount' => '250.00',
            'currency' => '504',
            'Response' => 'Approved',
            'TransId' => 'T'.$order->id,
            'hashAlgorithm' => 'ver3',
            'rnd' => 'r'.$order->id,
            ...$overrides,
        ];
        $params['HASH'] = CmiGateway::hash($params, self::STORE_KEY);

        return $params;
    }

    private function url(string $path): string
    {
        return "http://{$this->tenant->slug}.example.test{$path}";
    }
}
