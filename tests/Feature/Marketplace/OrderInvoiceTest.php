<?php

namespace Tests\Feature\Marketplace;

use App\Events\PaymentCompleted;
use App\Events\PaymentRefunded;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Every paid store order gets an invoice: in the workspace's Invoices
 * list, and printable by the buyer from "My purchases".
 */
class OrderInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $owner;

    private User $buyer;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tenancy.central_domain' => 'example.test',
            'tenancy.central_fallback_tenant' => null,
        ]);

        $this->owner = User::factory()->create();
        $this->buyer = User::factory()->create(['name' => 'Bea Buyer', 'email' => 'bea@example.com']);
        $this->tenant = Tenant::factory()->forOwner($this->owner)->create();
        $this->tenant->users()->attach($this->owner->id, ['role' => Tenant::ROLE_OWNER, 'joined_at' => now()]);
    }

    protected function tearDown(): void
    {
        app(TenantContext::class)->set(null);
        parent::tearDown();
    }

    private function url(string $path): string
    {
        return "http://{$this->tenant->slug}.example.test{$path}";
    }

    /**
     * @return array{0: Order, 1: Payment}
     */
    private function paidOrder(array $attributes = []): array
    {
        $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'title' => 'Laravel CRM']);
        $order = Order::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->buyer->id,
            'status' => Order::STATUS_PAID,
            'currency' => 'USD',
            'subtotal' => '100.00',
            'discount' => '10.00',
            'tax' => '0.00',
            'total' => '90.00',
            'billing_name' => 'Bea Buyer',
            'billing_email' => 'bea@example.com',
            'billing_country' => 'MA',
            'paid_at' => now(),
            ...$attributes,
        ]);
        $order->items()->create([
            'tenant_id' => $this->tenant->id,
            'product_id' => $product->id,
            'product_title' => 'Laravel CRM',
            'product_type' => $product->type,
            'quantity' => 2,
            'unit_price' => '50.00',
            'total_price' => '100.00',
            'metadata' => ['license' => 'extended'],
        ]);
        $payment = Payment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'order_id' => $order->id,
            'user_id' => $this->buyer->id,
            'status' => Payment::STATUS_SUCCEEDED,
        ]);

        return [$order, $payment];
    }

    private function invoiceFor(Order $order): ?Invoice
    {
        return Invoice::query()->forTenant($this->tenant)->where('order_id', $order->id)->first();
    }

    public function test_a_paid_order_gets_exactly_one_invoice_even_without_tenant_context(): void
    {
        [$order, $payment] = $this->paidOrder();

        // The Stripe webhook dispatches with no tenant in context — and may retry.
        app(TenantContext::class)->set(null);
        PaymentCompleted::dispatch($payment, $order);
        PaymentCompleted::dispatch($payment, $order);

        $this->assertSame(1, Invoice::query()->forTenant($this->tenant)->count());
        $invoice = $this->invoiceFor($order);
        $this->assertSame('INV-'.now()->year.'-0001', $invoice->number);
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame($this->buyer->id, $invoice->client_id);
        $this->assertSame([10000, 1000, 0, 9000], [$invoice->subtotal_cents, $invoice->discount_cents, $invoice->tax_cents, $invoice->total_cents]);
        $this->assertSame('USD', $invoice->currency);
        $this->assertSame([[
            'description' => 'Laravel CRM',
            'license' => 'extended',
            'qty' => 2,
            'unit_cents' => 5000,
            'total_cents' => 10000,
        ]], $invoice->line_items);
        $this->assertNotNull($invoice->paid_at);
    }

    public function test_amounts_are_stored_in_the_currencys_own_minor_unit(): void
    {
        [$order, $payment] = $this->paidOrder(['currency' => 'JPY', 'subtotal' => '5000', 'discount' => '0', 'total' => '5000']);

        PaymentCompleted::dispatch($payment, $order);

        $this->assertSame(5000, $this->invoiceFor($order)->total_cents);
    }

    public function test_numbers_continue_after_a_deleted_invoice(): void
    {
        $deleted = Invoice::factory()->create([
            'tenant_id' => $this->tenant->id,
            'number' => 'INV-'.now()->year.'-0001',
            'issued_on' => now(),
            'due_on' => now(),
        ]);
        $deleted->delete();

        [$order, $payment] = $this->paidOrder();
        PaymentCompleted::dispatch($payment, $order);

        $this->assertSame('INV-'.now()->year.'-0002', $this->invoiceFor($order)->number);
    }

    public function test_a_full_refund_voids_the_invoice(): void
    {
        [$order, $payment] = $this->paidOrder();
        PaymentCompleted::dispatch($payment, $order);

        $order->forceFill(['status' => Order::STATUS_REFUNDED, 'refunded_at' => now()])->save();
        PaymentRefunded::dispatch($payment, $order, 9000);

        $this->assertSame(Invoice::STATUS_VOID, $this->invoiceFor($order)->status);
    }

    public function test_the_backfill_command_covers_earlier_paid_orders_only_once(): void
    {
        [$paid] = $this->paidOrder();
        [$pending] = $this->paidOrder(['status' => Order::STATUS_PENDING, 'paid_at' => null]);

        $this->artisan('invoices:from-orders')->expectsOutput('1 invoice created.')->assertSuccessful();
        $this->artisan('invoices:from-orders')->expectsOutput('0 invoices created.')->assertSuccessful();

        $this->assertNotNull($this->invoiceFor($paid));
        $this->assertNull($this->invoiceFor($pending));
    }

    public function test_the_buyer_and_the_workspace_can_open_the_invoice_but_nobody_else(): void
    {
        [$order, $payment] = $this->paidOrder();
        PaymentCompleted::dispatch($payment, $order);
        $invoice = $this->invoiceFor($order);
        $path = $this->url('/invoices/'.$invoice->id);

        $this->actingAs($this->buyer)->get($path)
            ->assertOk()
            ->assertSee($invoice->number)
            ->assertSee('Laravel CRM')
            ->assertSee($order->order_number)
            ->assertSee('Bea Buyer');

        $this->actingAs($this->owner)->get($path)->assertOk();

        $this->actingAs(User::factory()->create())->get($path)->assertNotFound();
    }

    public function test_store_invoices_cannot_be_deleted_from_the_workspace(): void
    {
        [$order, $payment] = $this->paidOrder();
        PaymentCompleted::dispatch($payment, $order);
        $invoice = $this->invoiceFor($order);

        app(TenantContext::class)->set($this->tenant);
        $this->actingAs($this->owner)->delete($this->url('/workspace/invoices/'.$invoice->id))->assertSessionHas('error');

        $this->assertNotSoftDeleted($invoice);
    }

    public function test_the_workspace_list_and_my_purchases_link_the_invoice(): void
    {
        [$order, $payment] = $this->paidOrder();
        PaymentCompleted::dispatch($payment, $order);
        $invoice = $this->invoiceFor($order);
        app(TenantContext::class)->set($this->tenant);

        $this->actingAs($this->owner)->get($this->url('/workspace/invoices'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('invoices.data.0.order.order_number', $order->order_number)
                ->where('invoices.data.0.order_id', $order->id));

        $this->actingAs($this->buyer)->get($this->url('/purchases'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('purchases.data.0.invoice_url', fn ($url) => str_ends_with((string) $url, '/invoices/'.$invoice->id)));
    }
}
