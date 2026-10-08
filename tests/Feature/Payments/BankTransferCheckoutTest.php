<?php

namespace Tests\Feature\Payments;

use App\Domain\Billing\StripeGateway;
use App\Models\Download;
use App\Models\Invoice;
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
 * Bank transfer: the order is placed unpaid, the buyer sees the bank
 * details with the order number as reference, and an admin marks it paid
 * when the money arrives — then it is fulfilled, emailed and invoiced like
 * any paid order. Works for every currency (e.g. MAD, which PayPal refuses).
 */
class BankTransferCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $buyer;

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
                throw new \LogicException('Stripe must not be called for a bank transfer.');
            }
        });
    }

    public function test_checkout_offers_bank_transfer_for_a_dirham_cart(): void
    {
        $this->bankGateway();
        $this->addToCart(46.16, 'MAD');

        $this->actingAs($this->buyer)->get($this->url('/checkout'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('currency', 'MAD')
                ->where('bankTransfer', true)
                ->where('paypal', false));
    }

    public function test_bank_transfer_is_not_offered_without_an_account_number_or_iban(): void
    {
        $this->bankGateway(['account_name' => 'Acme SARL']);
        $this->addToCart(20.00, 'MAD');

        $this->actingAs($this->buyer)->get($this->url('/checkout'))
            ->assertInertia(fn (Assert $page) => $page->where('bankTransfer', false));

        $this->actingAs($this->buyer)
            ->postJson($this->url('/checkout'), $this->billing())
            ->assertStatus(503);
    }

    public function test_placing_the_order_shows_the_bank_details_and_leaves_it_unpaid(): void
    {
        $this->bankGateway();
        $this->addToCart(46.16, 'MAD');

        $response = $this->actingAs($this->buyer)->postJson($this->url('/checkout'), $this->billing())->assertOk();
        $order = Order::query()->forTenant($this->tenant)->sole();

        $this->assertSame(Order::STATUS_PENDING, $order->status);
        $this->assertSame('bank_transfer', $order->payment_method);
        $this->assertSame('MAD', $order->currency);
        $this->assertSame('bank:'.$order->order_number, $order->payments()->sole()->gateway_payment_id);
        $this->assertSame($this->url("/checkout/{$order->order_number}/confirmation"), $response->json('redirect_url'));

        $this->actingAs($this->buyer)->get($response->json('redirect_url'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('checkout/confirmation')
                ->where('order.status', Order::STATUS_PENDING)
                ->where('bankTransfer.iban', 'MA64 0000 0000 0000 0000 0000 000')
                ->where('bankTransfer.account_name', 'Acme SARL'));

        // Nothing delivered before the money arrives.
        $this->assertSame(0, Download::query()->forTenant($this->tenant)->count());
    }

    public function test_an_admin_marks_it_paid_which_fulfils_emails_and_invoices_once(): void
    {
        Notification::fake();
        $this->bankGateway();
        $this->addToCart(46.16, 'MAD');
        $this->actingAs($this->buyer)->postJson($this->url('/checkout'), $this->billing())->assertOk();
        $order = Order::query()->forTenant($this->tenant)->sole();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get($this->url('/admin/orders'))
            ->assertInertia(fn (Assert $page) => $page->where('orders.data.0.awaiting_transfer', true));

        $this->actingAs($admin)->post($this->url("/admin/orders/{$order->id}/mark-paid"))->assertSessionHas('success');
        // A second click changes nothing.
        $this->actingAs($admin)->post($this->url("/admin/orders/{$order->id}/mark-paid"))->assertSessionHas('error');

        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertSame(Payment::STATUS_SUCCEEDED, $order->payments()->sole()->status);
        $this->assertSame(1, Download::query()->forTenant($this->tenant)->count());
        $this->assertSame(1, Invoice::query()->forTenant($this->tenant)->where('order_id', $order->id)->count());
        Notification::assertSentOnDemandTimes(OrderConfirmation::class, 1);
    }

    public function test_only_admins_can_mark_orders_paid(): void
    {
        $this->bankGateway();
        $this->addToCart(46.16, 'MAD');
        $this->actingAs($this->buyer)->postJson($this->url('/checkout'), $this->billing())->assertOk();
        $order = Order::query()->forTenant($this->tenant)->sole();

        $this->actingAs($this->buyer)->post($this->url("/admin/orders/{$order->id}/mark-paid"))->assertForbidden();

        $this->assertSame(Order::STATUS_PENDING, $order->refresh()->status);
    }

    private function bankGateway(?array $credentials = null): PaymentGateway
    {
        return PaymentGateway::factory()->create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'bank_transfer',
            'name' => 'Bank transfer',
            'display_name' => 'Bank transfer',
            'environment' => PaymentGateway::ENV_PRODUCTION,
            'is_active' => true,
            'credentials' => $credentials ?? [
                'account_name' => 'Acme SARL',
                'bank_name' => 'Attijariwafa bank',
                'iban' => 'MA64 0000 0000 0000 0000 0000 000',
                'swift' => 'BCMAMAMC',
            ],
        ]);
    }

    private function addToCart(float $price, string $currency): void
    {
        $product = Product::factory()->digitalDownload()->create([
            'tenant_id' => $this->tenant->id,
            'status' => Product::STATUS_PUBLISHED,
            'price' => $price,
            'sale_price' => null,
            'currency' => $currency,
        ]);

        $this->actingAs($this->buyer)->post($this->url('/cart'), ['product_id' => $product->id, 'quantity' => 1]);
    }

    /**
     * @return array<string, string>
     */
    private function billing(): array
    {
        return ['billing_name' => 'Ada Lovelace', 'billing_email' => 'ada@example.test', 'payment_method' => 'bank_transfer'];
    }

    private function url(string $path): string
    {
        return "http://{$this->tenant->slug}.example.test{$path}";
    }
}
