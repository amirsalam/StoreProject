<?php

namespace App\Domain\Marketplace;

use App\Domain\Billing\MissingStripeKeysException;
use App\Domain\Billing\StripeGateway;
use App\Domain\Payments\Cmi\CmiGateway;
use App\Domain\Payments\OrderPaymentProcessor;
use App\Domain\Payments\PayPal\PayPalGateway;
use App\Domain\Payments\PayPal\PayPalPayments;
use App\Events\PaymentCompleted;
use App\Listeners\FulfillOrder;
use App\Models\Coupon;
use App\Models\License;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Support\Money;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Stripe\Exception\ApiErrorException;

/**
 * Turns the session cart into a persisted order + a pending payment, and
 * hands back the Stripe PaymentIntent client secret for card confirmation.
 *
 * This is the "left half" the {@see OrderPaymentProcessor}
 * was always designed to meet: it creates the Payment row (with a
 * gateway_payment_id) that the webhook processor later locks, flips to
 * succeeded, and follows by marking the order paid + crediting the wallet.
 * Fulfillment (licenses, downloads) then fans out from the PaymentCompleted
 * event via {@see FulfillOrder}.
 *
 * Money invariants:
 *   - Prices are read SERVER-SIDE from the cart's published products; the
 *     client never supplies a price.
 *   - Order + items + coupon increment commit in one transaction.
 *   - The amount handed to Stripe is the integer-cents total (Money),
 *     never a float.
 */
class CheckoutService
{
    /** The order's currency: the cart's (all its products share one). */
    private string $currency = 'USD';

    public const METHOD_STRIPE = 'stripe';

    public const METHOD_CMI = 'cmi';

    /** PayPal: approval on paypal.com, captured when the buyer returns. */
    public const METHOD_PAYPAL = 'paypal';

    public function __construct(
        private readonly CartService $cart,
        private readonly StripeGateway $gateway,
        private readonly CmiGateway $cmi,
        private readonly PayPalGateway $paypal,
        private readonly PayPalPayments $paypalPayments,
    ) {}

    /**
     * @param  array{billing_name: string, billing_email: string, billing_country?: ?string, billing_address?: ?array, notes?: ?string}  $billing
     *
     * @throws EmptyCartException
     * @throws InvalidCouponException
     */
    public function placeOrder(User $user, array $billing, ?string $couponCode = null, string $method = self::METHOD_STRIPE): CheckoutResult
    {
        $lineItems = $this->cart->lineItems();

        if ($lineItems->isEmpty()) {
            throw new EmptyCartException;
        }

        $this->currency = $this->cart->currency() ?? 'USD';
        $subtotal = round((float) $lineItems->sum('line_total'), 2);
        [$coupon, $discount] = $this->resolveCoupon($couponCode, $user, $subtotal);
        $total = max(0.0, round($subtotal - $discount, 2));

        // CMI: the buyer pays on CMI's hosted page after this request, so all
        // we need now is a configured gateway to send them to.
        $cmiGateway = null;
        if ($method === self::METHOD_CMI) {
            // CMI's rate is set as 1 USD = X MAD, so it takes USD carts only.
            $cmiGateway = ($this->currency === 'USD' ? $this->cmi->gateway() : null)
                ?? throw new PaymentUnavailableException(__('messages.checkout.payments_unavailable'));
        }

        // PayPal: same idea — a configured gateway, then a PayPal order.
        $paypalGateway = null;
        if ($method === self::METHOD_PAYPAL) {
            $paypalGateway = (PayPalGateway::supportsCurrency($this->currency) ? $this->paypal->gateway() : null)
                ?? throw new PaymentUnavailableException(__('messages.checkout.payments_unavailable'));
        }

        // Order, items, coupon redemption, payment row and the Stripe intent
        // are one unit: if Stripe refuses (bad keys, outage) nothing is left
        // behind — no orphan pending order, no burnt coupon use.
        try {
            [$order, $payment, $intent] = DB::transaction(
                fn () => $this->createOrderWithIntent($user, $billing, $lineItems, $subtotal, $discount, $total, $coupon, $cmiGateway, $paypalGateway),
            );
        } catch (ApiErrorException|MissingStripeKeysException|RequestException|ConnectionException $e) {
            report($e);

            throw new PaymentUnavailableException(__('messages.checkout.payments_unavailable'), previous: $e);
        }

        // Fully-discounted ($0) orders have nothing to charge — settle them
        // immediately and let fulfillment fan out, rather than opening a
        // Stripe intent for zero.
        // (Decided on the amount, not a missing Stripe intent: CMI orders have
        // no intent either, and must never be settled for free.)
        if ($total <= 0) {
            $this->settleFreeOrder($order, $payment);
            $this->cart->clear();

            return new CheckoutResult($order->refresh(), null);
        }

        $this->cart->clear();

        if ($cmiGateway) {
            // The browser is sent on to CMI's hosted payment page.
            return new CheckoutResult($order, null, route('checkout.cmi.redirect', $order->order_number));
        }

        if ($paypalGateway) {
            // The browser is sent on to PayPal to approve the payment.
            return new CheckoutResult($order, null, $intent['paypal_url']);
        }

        return new CheckoutResult($order, $intent['client_secret']);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $lineItems
     * @param  array<string, mixed>  $billing
     * @return array{0: Order, 1: Payment, 2: array{id: string, client_secret: string|null, customer: string|null}|null}
     */
    private function createOrderWithIntent(User $user, array $billing, Collection $lineItems, float $subtotal, float $discount, float $total, ?Coupon $coupon, ?PaymentGateway $cmiGateway = null, ?PaymentGateway $paypalGateway = null): array
    {
        $order = Order::create([
            'user_id' => $user->id,
            'coupon_id' => $coupon?->id,
            'subtotal' => $subtotal,
            'discount' => $discount,
            'tax' => 0,
            'total' => $total,
            'currency' => $this->currency,
            'status' => Order::STATUS_PENDING,
            'payment_method' => $paypalGateway ? self::METHOD_PAYPAL : ($cmiGateway ? self::METHOD_CMI : self::METHOD_STRIPE),
            'billing_name' => $billing['billing_name'],
            'billing_email' => $billing['billing_email'],
            'billing_country' => $billing['billing_country'] ?? null,
            'billing_address' => $billing['billing_address'] ?? null,
            'notes' => $billing['notes'] ?? null,
        ]);

        foreach ($lineItems as $row) {
            /** @var Product $product */
            $product = $row['product'];

            $order->items()->create([
                'product_id' => $product->id,
                'product_title' => $product->title,
                'product_type' => $product->type,
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'total_price' => $row['line_total'],
                // Purchase options, read at fulfilment (license tier) and on
                // My purchases (support period).
                'metadata' => [
                    'license' => ($row['extended'] ?? false) ? License::TIER_EXTENDED : License::TIER_REGULAR,
                    'extended_support' => (bool) ($row['extended_support'] ?? false),
                    'support_months' => (int) ($row['support_months'] ?? $product->support_months),
                ],
            ]);
        }

        if ($coupon) {
            // Atomic increment so concurrent redemptions can't oversell a
            // limited coupon past max_uses.
            $coupon->increment('used_count');
        }

        // The pending payment the webhook processor will find by
        // gateway_payment_id. tenant_id is auto-filled by BelongsToTenant.
        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => $paypalGateway ? self::METHOD_PAYPAL : ($cmiGateway ? self::METHOD_CMI : self::METHOD_STRIPE),
            'amount' => $total,
            'currency' => $this->currency,
            'status' => Payment::STATUS_PENDING,
            'payment_method' => 'card',
        ]);

        if ($total <= 0) {
            return [$order, $payment, null];
        }

        if ($cmiGateway) {
            // CMI identifies the payment by our order number (oid). The MAD
            // amount and the rate used are recorded so the callback can be
            // checked against exactly what the buyer was asked to pay.
            $payment->update([
                'gateway_payment_id' => $order->order_number,
                'raw_response' => ['cmi' => [
                    'amount_mad' => $this->cmi->madAmount($total, $cmiGateway),
                    'rate' => $this->cmi->rate($cmiGateway),
                ]],
            ]);

            return [$order, $payment, null];
        }

        if ($paypalGateway) {
            // Inside the transaction: if PayPal refuses, nothing is kept.
            return [$order, $payment, ['paypal_url' => $this->paypalPayments->start($order, $payment, $paypalGateway)]];
        }

        // Inside the transaction on purpose: if Stripe throws, the order,
        // items, coupon use and payment above are rolled back.
        $intent = $this->gateway->createPaymentIntent(
            self::stripeAmount($total, $this->currency),
            $this->currency,
            $this->intentMetadata($order, $payment),
        );

        $payment->update([
            'gateway_payment_id' => $intent['id'],
            'gateway_customer_id' => $intent['customer'],
        ]);

        return [$order, $payment, $intent];
    }

    /**
     * The amount Stripe expects: the currency's smallest unit (cents, yen …).
     * Stripe takes 3-decimal currencies (KWD, BHD …) only in multiples of 10.
     */
    public static function stripeAmount(float|string $total, string $currency): int
    {
        $minor = Money::toMinor($total, $currency);

        return Money::minorUnits($currency) === 3 ? (int) (round($minor / 10) * 10) : $minor;
    }

    /**
     * Resolve + validate a coupon code against the running subtotal.
     *
     * @return array{0: ?Coupon, 1: float}
     *
     * @throws InvalidCouponException
     */
    private function resolveCoupon(?string $code, User $user, float $subtotal): array
    {
        $code = $code !== null ? trim($code) : '';
        if ($code === '') {
            return [null, 0.0];
        }

        // Coupon is BelongsToTenant — this lookup is tenant-scoped.
        $coupon = Coupon::query()->where('code', $code)->first();

        if (! $coupon) {
            throw new InvalidCouponException(__('messages.checkout.coupon_errors.invalid'));
        }

        // Say why, so a buyer isn't left guessing. A disabled coupon still
        // gets the generic message — no need to confirm the code exists.
        $date = fn (?\DateTimeInterface $d) => $d ? Carbon::instance($d)->locale(app()->getLocale())->isoFormat('LL') : '';
        $reason = match ($coupon->unusableReason()) {
            Coupon::UNUSABLE_INACTIVE => __('messages.checkout.coupon_errors.invalid'),
            Coupon::UNUSABLE_NOT_STARTED => __('messages.checkout.coupon_errors.not_started', ['date' => $date($coupon->starts_at)]),
            Coupon::UNUSABLE_EXPIRED => __('messages.checkout.coupon_errors.expired', ['date' => $date($coupon->expires_at)]),
            Coupon::UNUSABLE_USED_UP => __('messages.checkout.coupon_errors.used_up'),
            default => null,
        };
        if ($reason !== null) {
            throw new InvalidCouponException($reason);
        }

        if ($coupon->min_order_amount !== null && $subtotal < (float) $coupon->min_order_amount) {
            throw new InvalidCouponException(__('messages.checkout.coupon_errors.min_order', [
                'amount' => Money::format($coupon->min_order_amount, $this->currency),
            ]));
        }

        if ($coupon->max_uses_per_user !== null) {
            $usedByUser = Order::query()
                ->where('user_id', $user->id)
                ->where('coupon_id', $coupon->id)
                ->count();

            if ($usedByUser >= $coupon->max_uses_per_user) {
                throw new InvalidCouponException(__('messages.checkout.coupon_errors.already_used'));
            }
        }

        return [$coupon, $coupon->discountFor($subtotal)];
    }

    /**
     * Mark a $0 order paid and emit PaymentCompleted so fulfillment runs.
     * No wallet credit (the amount is zero) and no Stripe round-trip.
     */
    private function settleFreeOrder(Order $order, Payment $payment): void
    {
        DB::transaction(function () use ($order, $payment) {
            $payment->update([
                'status' => Payment::STATUS_SUCCEEDED,
                'gateway_payment_id' => 'free_'.$order->order_number,
                'processed_at' => now(),
            ]);

            $order->update([
                'status' => Order::STATUS_PAID,
                'paid_at' => now(),
            ]);
        });

        PaymentCompleted::dispatch($payment, $order);
    }

    /**
     * @return array<string, string>
     */
    private function intentMetadata(Order $order, Payment $payment): array
    {
        $meta = [
            'order_id' => (string) $order->id,
            'payment_id' => (string) $payment->id,
            'order_number' => $order->order_number,
        ];

        // The OrderPaymentProcessor reads data.object.metadata.tenant_id.
        if ($order->tenant_id !== null) {
            $meta['tenant_id'] = (string) $order->tenant_id;
        }

        return $meta;
    }
}
