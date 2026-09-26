<?php

namespace App\Domain\Marketplace;

use App\Domain\Billing\StripeGateway;
use App\Domain\Payments\OrderPaymentProcessor;
use App\Events\PaymentCompleted;
use App\Listeners\FulfillOrder;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\CartService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

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
    private const CURRENCY = 'USD';

    public function __construct(
        private readonly CartService $cart,
        private readonly StripeGateway $gateway,
    ) {}

    /**
     * @param  array{billing_name: string, billing_email: string, billing_country?: ?string, billing_address?: ?array, notes?: ?string}  $billing
     *
     * @throws EmptyCartException
     * @throws InvalidCouponException
     */
    public function placeOrder(User $user, array $billing, ?string $couponCode = null): CheckoutResult
    {
        $lineItems = $this->cart->lineItems();

        if ($lineItems->isEmpty()) {
            throw new EmptyCartException;
        }

        $subtotal = round((float) $lineItems->sum('line_total'), 2);
        [$coupon, $discount] = $this->resolveCoupon($couponCode, $user, $subtotal);
        $total = max(0.0, round($subtotal - $discount, 2));

        $order = DB::transaction(function () use ($user, $billing, $lineItems, $subtotal, $discount, $total, $coupon) {
            $order = Order::create([
                'user_id' => $user->id,
                'coupon_id' => $coupon?->id,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'tax' => 0,
                'total' => $total,
                'currency' => self::CURRENCY,
                'status' => Order::STATUS_PENDING,
                'payment_method' => 'stripe',
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
                ]);
            }

            if ($coupon) {
                // Atomic increment so concurrent redemptions can't oversell a
                // limited coupon past max_uses.
                $coupon->increment('used_count');
            }

            return $order;
        });

        // The pending payment the webhook processor will find by
        // gateway_payment_id. tenant_id is auto-filled by BelongsToTenant.
        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'gateway' => 'stripe',
            'amount' => $total,
            'currency' => self::CURRENCY,
            'status' => Payment::STATUS_PENDING,
            'payment_method' => 'card',
        ]);

        // Fully-discounted ($0) orders have nothing to charge — settle them
        // immediately and let fulfillment fan out, rather than opening a
        // Stripe intent for zero.
        if ($total <= 0) {
            $this->settleFreeOrder($order, $payment);
            $this->cart->clear();

            return new CheckoutResult($order->refresh(), null);
        }

        $intent = $this->gateway->createPaymentIntent(
            Money::toCents((string) $total),
            self::CURRENCY,
            $this->intentMetadata($order, $payment),
        );

        $payment->update([
            'gateway_payment_id' => $intent['id'],
            'gateway_customer_id' => $intent['customer'],
        ]);

        $this->cart->clear();

        return new CheckoutResult($order, $intent['client_secret']);
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

        if (! $coupon || ! $coupon->isUsable()) {
            throw new InvalidCouponException("That coupon code isn't valid.");
        }

        if ($coupon->min_order_amount !== null && $subtotal < (float) $coupon->min_order_amount) {
            throw new InvalidCouponException(
                'Your order does not meet the minimum for this coupon.'
            );
        }

        if ($coupon->max_uses_per_user !== null) {
            $usedByUser = Order::query()
                ->where('user_id', $user->id)
                ->where('coupon_id', $coupon->id)
                ->count();

            if ($usedByUser >= $coupon->max_uses_per_user) {
                throw new InvalidCouponException("You've already used this coupon.");
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
