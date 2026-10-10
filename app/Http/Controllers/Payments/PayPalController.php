<?php

namespace App\Http\Controllers\Payments;

use App\Domain\Marketplace\CheckoutService;
use App\Domain\Payments\PayPal\PayPalGateway;
use App\Domain\Payments\PayPal\PayPalPayments;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * The buyer's way back from PayPal (approved or cancelled), and "try
 * again" for a pending PayPal order. Only the order's own buyer gets in.
 */
class PayPalController extends Controller
{
    public function __construct(private readonly PayPalPayments $payments) {}

    /**
     * PayPal sends the buyer here after approving: capture now.
     */
    public function return(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeBuyer($request, $order);

        $result = $this->payments->complete($order, $request->query('token'));

        return redirect()->route('checkout.confirmation', array_filter([
            'order' => $order->order_number,
            'payment' => $result === PayPalPayments::PAID ? null : 'failed',
        ]));
    }

    /**
     * The buyer cancelled on PayPal: back to the order, which stays pending.
     */
    public function cancel(Request $request, Order $order): RedirectResponse
    {
        $this->authorizeBuyer($request, $order);

        return redirect()->route('checkout.confirmation', ['order' => $order->order_number, 'payment' => 'failed']);
    }

    /**
     * Pay a pending order with PayPal: "try again" after a cancelled PayPal
     * payment, or "pay with PayPal instead" from the card step (the order
     * then switches to PayPal; its unconfirmed card payment is left unused).
     */
    public function pay(Request $request, Order $order, PayPalGateway $paypal): RedirectResponse
    {
        $this->authorizeBuyer($request, $order);

        $switchable = [CheckoutService::METHOD_PAYPAL, CheckoutService::METHOD_STRIPE];
        if ($order->status !== Order::STATUS_PENDING || ! in_array($order->payment_method, $switchable, true)) {
            return redirect()->route('checkout.confirmation', $order->order_number);
        }

        $gateway = $paypal->gateway();
        if (! $gateway) {
            return redirect()->route('checkout.confirmation', ['order' => $order->order_number, 'payment' => 'failed']);
        }

        $payment = $order->payments()->where('gateway', 'paypal')->where('status', Payment::STATUS_PENDING)->latest('id')->first()
            ?? $order->payments()->create([
                'user_id' => $order->user_id,
                'gateway' => CheckoutService::METHOD_PAYPAL,
                'amount' => $order->total,
                'currency' => $order->currency,
                'status' => Payment::STATUS_PENDING,
                'payment_method' => 'paypal',
            ]);

        if ($order->payment_method !== CheckoutService::METHOD_PAYPAL) {
            $order->update(['payment_method' => CheckoutService::METHOD_PAYPAL]);
        }

        try {
            return redirect()->away($this->payments->start($order, $payment, $gateway));
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('checkout.confirmation', ['order' => $order->order_number, 'payment' => 'failed']);
        }
    }

    private function authorizeBuyer(Request $request, Order $order): void
    {
        abort_unless($order->user_id === $request->user()->id, 404);
    }
}
