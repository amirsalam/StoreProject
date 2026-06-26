<?php

namespace App\Http\Controllers;

use App\Domain\Marketplace\CheckoutService;
use App\Domain\Marketplace\EmptyCartException;
use App\Domain\Marketplace\InvalidCouponException;
use App\Http\Requests\Marketplace\PlaceOrderRequest;
use App\Models\Order;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Checkout: turns the session cart into an order + a Stripe PaymentIntent,
 * then a confirmation page. The order starts `pending`; the shipped
 * StripeWebhookController -> OrderPaymentProcessor flips it to `paid` and
 * triggers fulfillment once Stripe confirms the charge.
 */
class CheckoutController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CheckoutService $checkout,
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
        ]);
    }

    public function store(PlaceOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        try {
            $result = $this->checkout->placeOrder(
                $request->user(),
                $data,
                $data['coupon_code'] ?? null,
            );
        } catch (EmptyCartException $e) {
            return redirect()->route('cart.show')->withErrors(['cart' => $e->getMessage()]);
        } catch (InvalidCouponException $e) {
            return back()->withErrors(['coupon_code' => $e->getMessage()])->withInput();
        }

        return redirect()->route('checkout.confirmation', $result->order->order_number);
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
