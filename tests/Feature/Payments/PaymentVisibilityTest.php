<?php

namespace Tests\Feature\Payments;

use App\Domain\Billing\StripeGateway;
use App\Domain\Dashboard\MetricsAggregator;
use App\Models\DailyMetric;
use App\Models\License;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * After a successful card payment the order must become paid and show up:
 * confirmed on return (no webhook needed), reflected in the dashboard
 * rollup, and listed in Admin → Orders.
 */
class PaymentVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_returning_to_the_confirmation_page_confirms_a_succeeded_payment(): void
    {
        $this->fakeIntentStatus('succeeded');
        [$user, $order] = $this->pendingLicenseOrder('pi_paid');

        $this->actingAs($user)
            ->get(route('checkout.confirmation', $order->order_number))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('order.status', Order::STATUS_PAID));

        $this->assertSame(Payment::STATUS_SUCCEEDED, $order->payments()->first()->status);
        $this->assertSame(1, License::query()->where('order_item_id', $order->items()->first()->id)->count());

        // A second visit (or the webhook arriving later) changes nothing.
        $this->actingAs($user)->get(route('checkout.confirmation', $order->order_number))->assertOk();
        $this->assertSame(1, License::query()->where('order_item_id', $order->items()->first()->id)->count());
    }

    public function test_an_unfinished_payment_leaves_the_order_pending(): void
    {
        $this->fakeIntentStatus('requires_payment_method');
        [$user, $order] = $this->pendingLicenseOrder('pi_open');

        $this->actingAs($user)
            ->get(route('checkout.confirmation', $order->order_number))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('order.status', Order::STATUS_PENDING));
    }

    public function test_a_paid_order_updates_the_dashboard_rollup_immediately(): void
    {
        $this->fakeIntentStatus('succeeded');
        [$user, $order] = $this->pendingLicenseOrder('pi_metrics');
        $version = (int) Cache::get('cache:dashboard:version', 1);

        $this->actingAs($user)->get(route('checkout.confirmation', $order->order_number))->assertOk();

        $platformRevenue = DB::table('daily_metrics')
            ->whereNull('tenant_id')
            ->where('metric_key', DailyMetric::KEY_REVENUE_CENTS)
            ->where('recorded_on', CarbonImmutable::today()->toDateString())
            ->value('value');
        $this->assertSame(2500, (int) $platformRevenue);
        $this->assertGreaterThan($version, (int) Cache::get('cache:dashboard:version', 1));
    }

    public function test_the_rollup_counts_every_store_even_with_a_tenant_in_context(): void
    {
        $a = Tenant::factory()->create();
        $b = Tenant::factory()->create();
        foreach ([$a, $b] as $tenant) {
            Order::factory()->create(['tenant_id' => $tenant->id, 'status' => Order::STATUS_PAID, 'total' => 10, 'paid_at' => now()]);
        }

        // As in a web request on store A's host.
        app(TenantContext::class)->set($a);
        app(MetricsAggregator::class)->rebuildDay(CarbonImmutable::today());

        $platform = DB::table('daily_metrics')->whereNull('tenant_id')
            ->where('metric_key', DailyMetric::KEY_REVENUE_CENTS)
            ->where('recorded_on', CarbonImmutable::today()->toDateString())->value('value');
        $storeB = DB::table('daily_metrics')->where('tenant_id', $b->id)
            ->where('metric_key', DailyMetric::KEY_REVENUE_CENTS)
            ->where('recorded_on', CarbonImmutable::today()->toDateString())->value('value');

        $this->assertSame(2000, (int) $platform);
        $this->assertSame(1000, (int) $storeB);
    }

    public function test_admins_see_orders_with_their_payment(): void
    {
        [, $order] = $this->pendingLicenseOrder('pi_listed');

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.orders.index', ['search' => $order->order_number]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin/orders/index')
                ->has('orders.data', 1)
                ->where('orders.data.0.order_number', $order->order_number)
                ->where('orders.data.0.payment.reference', 'pi_listed')
                ->where('summary.pending', 1)
                ->where('mailConfigured', false)
            );
    }

    public function test_non_admins_cannot_see_orders(): void
    {
        $this->actingAs(User::factory()->create())->get(route('admin.orders.index'))->assertForbidden();
    }

    private function fakeIntentStatus(string $status): void
    {
        $this->app->instance(StripeGateway::class, new class($status) extends StripeGateway
        {
            public function __construct(private readonly string $status) {}

            public function retrievePaymentIntent(string $intentId): array
            {
                return [
                    'id' => $intentId,
                    'status' => $this->status,
                    'object' => ['id' => $intentId, 'object' => 'payment_intent', 'status' => $this->status],
                ];
            }
        });
    }

    /**
     * @return array{0: User, 1: Order}
     */
    private function pendingLicenseOrder(string $intentId): array
    {
        $user = User::factory()->create();
        $product = Product::factory()->license()->create(['default_activation_limit' => 1]);
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING,
            'total' => 25.00,
            'currency' => 'USD',
            'paid_at' => null,
        ]);
        $order->items()->create([
            'product_id' => $product->id,
            'product_title' => $product->title,
            'product_type' => $product->type,
            'quantity' => 1,
            'unit_price' => 25.00,
            'total_price' => 25.00,
        ]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => 25.00,
            'currency' => 'USD',
            'gateway' => 'stripe',
            'gateway_payment_id' => $intentId,
            'status' => Payment::STATUS_PENDING,
        ]);

        return [$user, $order];
    }
}
