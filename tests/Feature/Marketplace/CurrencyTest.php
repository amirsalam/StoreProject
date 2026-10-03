<?php

namespace Tests\Feature\Marketplace;

use App\Domain\Billing\StripeGateway;
use App\Domain\Marketplace\CheckoutService;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Products can be priced in any ISO 4217 currency, and orders are charged
 * in that currency — one currency per cart, amounts in the currency's own
 * smallest unit.
 */
class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    /** What the fake Stripe was asked to charge. */
    public static array $charged = [];

    protected function setUp(): void
    {
        parent::setUp();
        self::$charged = [];

        $this->app->instance(StripeGateway::class, new class extends StripeGateway
        {
            public function createPaymentIntent(int $amountCents, string $currency, array $metadata = []): array
            {
                CurrencyTest::$charged = ['amount' => $amountCents, 'currency' => $currency];

                return ['id' => 'pi_fake', 'client_secret' => 'pi_fake_secret', 'customer' => null];
            }
        });
    }

    public function test_the_product_form_accepts_any_iso_currency_and_nothing_else(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post(route('admin.products.store'), $this->fields(['currency' => 'eur']))->assertSessionHasNoErrors();
        $this->assertSame('EUR', Product::query()->where('slug', 'theme')->value('currency'));

        $this->actingAs($admin)->post(route('admin.products.store'), $this->fields(['slug' => 'theme-2', 'currency' => 'XYZ']))
            ->assertSessionHasErrors('currency');

        $this->actingAs($admin)->get(route('admin.products.create'))
            ->assertInertia(fn ($page) => $page->has('currencies', count(config('currencies'))));
    }

    public function test_a_cart_holds_one_currency(): void
    {
        $eur = Product::factory()->digitalDownload()->create(['status' => Product::STATUS_PUBLISHED, 'currency' => 'EUR']);
        $eur2 = Product::factory()->digitalDownload()->create(['status' => Product::STATUS_PUBLISHED, 'currency' => 'EUR']);
        $usd = Product::factory()->digitalDownload()->create(['status' => Product::STATUS_PUBLISHED, 'currency' => 'USD']);

        $this->post(route('cart.add'), ['product_id' => $eur->id])->assertSessionHasNoErrors();
        $this->post(route('cart.add'), ['product_id' => $eur2->id])->assertSessionHasNoErrors();
        $this->post(route('cart.add'), ['product_id' => $usd->id])->assertSessionHasErrors('cart');

        $cart = app(CartService::class);
        $this->assertSame(2, $cart->lineItems()->count());
        $this->assertSame('EUR', $cart->currency());
    }

    public function test_checkout_charges_in_the_carts_currency(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->digitalDownload()->create(['status' => Product::STATUS_PUBLISHED, 'currency' => 'JPY', 'price' => 5000, 'sale_price' => null]);

        $this->actingAs($buyer)->post(route('cart.add'), ['product_id' => $product->id]);
        $this->actingAs($buyer)->postJson(route('checkout.store'), ['billing_name' => 'Ada', 'billing_email' => 'ada@example.test'])->assertOk();

        $order = Order::query()->sole();
        $this->assertSame('JPY', $order->currency);
        $this->assertSame(['amount' => 5000, 'currency' => 'JPY'], self::$charged); // yen have no cents
    }

    public function test_amounts_use_each_currencys_smallest_unit(): void
    {
        $this->assertSame(4999, CheckoutService::stripeAmount(49.99, 'USD'));
        $this->assertSame(5000, CheckoutService::stripeAmount(5000, 'JPY'));
        $this->assertSame(1230, CheckoutService::stripeAmount(1.234, 'KWD')); // 3 decimals, multiple of 10 for Stripe
        $this->assertSame(0, Money::minorUnits('XOF'));
        $this->assertStringContainsString('€', Money::format(49.5, 'EUR', 'fr'));
    }

    public function test_paypal_and_cmi_are_only_offered_for_currencies_they_take(): void
    {
        $buyer = User::factory()->create();
        $product = Product::factory()->digitalDownload()->create(['status' => Product::STATUS_PUBLISHED, 'currency' => 'MAD']);
        $this->actingAs($buyer)->post(route('cart.add'), ['product_id' => $product->id]);

        $this->actingAs($buyer)->get(route('checkout.show'))
            ->assertInertia(fn ($page) => $page
                ->where('currency', 'MAD')
                ->where('paypal', false)   // PayPal doesn't take MAD
                ->where('cmi', null));     // CMI's rate is USD-based
    }

    private function fields(array $overrides = []): array
    {
        return [
            'title' => 'Theme',
            'slug' => 'theme',
            'type' => Product::TYPE_DIGITAL_DOWNLOAD,
            'price' => '49',
            'currency' => 'USD',
            'default_activation_limit' => 1,
            'status' => Product::STATUS_PUBLISHED,
            ...$overrides,
        ];
    }
}
