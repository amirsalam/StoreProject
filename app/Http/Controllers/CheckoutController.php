<?php

namespace App\Http\Controllers;

use App\Domain\Marketplace\CheckoutService;
use App\Domain\Marketplace\EmptyCartException;
use App\Domain\Marketplace\InvalidCouponException;
use App\Domain\Payments\StripeCredentials;
use App\Http\Requests\Marketplace\PlaceOrderRequest;
use App\Models\Order;
use App\Services\CartService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checkout: turns the session cart into an order + a Stripe PaymentIntent,
 * then a confirmation page. The order starts `pending`; StripeWebhookController
 * queues payment events to ProcessPaymentWebhook -> OrderPaymentProcessor,
 * which flips it to `paid` and triggers fulfillment once Stripe confirms the
 * charge (a queue worker must be running).
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CheckoutService $checkout,
        private readonly StripeCredentials $stripe,
    ) {}

    public function show(Request $request): Response|RedirectResponse
    {
        $items = $this->cart->lineItems();

        if ($items->isEmpty()) {
            return redirect()->route('cart.show');
        }

        $user = $request->user();

        return Inertia::render('checkout/index', [
            'items' => $items->map(fn ($row) => [
                'id' => $row['product']->id,
                'title' => $row['product']->title,
                'type' => $row['product']->type,
                'thumbnail' => $row['product']->thumbnail,
                'quantity' => $row['quantity'],
                'unit_price' => $row['unit_price'],
                'line_total' => $row['line_total'],
            ])->values(),
            'subtotal' => $this->cart->subtotal(),
            'currency' => 'USD',
            'buyer' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            // pk_test_… — the store's public publishable key (Admin → Payment
            // Gateways, else .env). Used by Stripe.js in the browser to mount
            // the PaymentElement. Null if payments are not configured (the
            // page then shows a "payments unavailable" state).
            'stripeKey' => $this->stripe->keys()['publishable_key'],
        ]);
    }

    /**
     * Place the order + open a Stripe PaymentIntent.
     *
     * Content-negotiated: the card-confirmation UI calls this with
     * `Accept: application/json` and gets back the intent `client_secret`
     * (which it confirms client-side via Stripe Elements). Plain requests
     * keep the original redirect-to-confirmation behaviour.
     */
    public function store(PlaceOrderRequest $request): RedirectResponse|JsonResponse
    {
        $data = $request->validated();

        try {
            $result = $this->checkout->placeOrder(
                $request->user(),
                $data,
                $data['coupon_code'] ?? null,
            );
        } catch (EmptyCartException $e) {
            if ($request->expectsJson()) {
                return response()->json(['message' => $e->getMessage(), 'redirect' => route('cart.show')], 422);
            }

            return redirect()->route('cart.show')->withErrors(['cart' => $e->getMessage()]);
        } catch (InvalidCouponException $e) {
            if ($request->expectsJson()) {
                return response()->json(['errors' => ['coupon_code' => [$e->getMessage()]]], 422);
            }

            return back()->withErrors(['coupon_code' => $e->getMessage()])->withInput();
        }

        $confirmationUrl = route('checkout.confirmation', $result->order->order_number);

        if ($request->expectsJson()) {
            return response()->json([
                'order_number' => $result->order->order_number,
                // null for a fully-discounted $0 order — already settled, so
                // the client skips the card step and goes straight to
                // confirmation.
                'client_secret' => $result->clientSecret,
                'confirmation_url' => $confirmationUrl,
            ]);
        }

        return redirect($confirmationUrl);
    }

    public function confirmation(Request $request, Order $order): Response
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $order->load('items');

        return Inertia::render('checkout/confirmation', [
            'order' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'subtotal' => (string) $order->subtotal,
                'discount' => (string) $order->discount,
                'total' => (string) $order->total,
                'currency' => $order->currency,
                'billing_email' => $order->billing_email,
                'paid_at' => $order->paid_at,
                'items' => $order->items->map(fn ($item) => [
                    'product_title' => $item->product_title,
                    'product_type' => $item->product_type,
                    'quantity' => $item->quantity,
                    'total_price' => (string) $item->total_price,
                ]),
            ],
        ]);
    }
}
