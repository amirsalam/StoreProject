<?php

namespace App\Http\Controllers\Payments;

use App\Domain\Payments\Cmi\CmiGateway;
use App\Domain\Payments\Cmi\CmiPayments;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\View\View;

/**
 * CMI hosted payment: send the buyer to CMI, then take their return and
 * CMI's server callback. okUrl/failUrl/callbackUrl are POSTed by CMI (a
 * different site), so they are CSRF-exempt and don't rely on the session —
 * every one is verified by CmiPayments instead.
 */
class CmiController extends Controller
{
    public function __construct(
        private readonly CmiGateway $cmi,
        private readonly CmiPayments $payments,
    ) {}

    /** Auto-submitting form that posts the signed payment request to CMI. */
    public function redirect(Request $request, Order $order): View|RedirectResponse
    {
        abort_unless($order->user_id === $request->user()->id, 403);

        $payment = $order->payments()->where('gateway', 'cmi')->where('status', Payment::STATUS_PENDING)->latest('id')->first();
        $gateway = $this->cmi->gateway();

        if ($order->status !== Order::STATUS_PENDING || ! $payment || ! $gateway) {
            return redirect()->route('checkout.confirmation', $order->order_number);
        }

        return view('payments.cmi-redirect', [
            'form' => $this->cmi->paymentForm($order, $payment, $gateway, App::getLocale()),
        ]);
    }

    /** CMI sends the browser here after an approved payment. */
    public function ok(Request $request, Order $order): RedirectResponse
    {
        $result = $this->payments->apply($order, $request->all());

        return redirect()->route('checkout.confirmation', array_filter([
            'order' => $order->order_number,
            'payment' => $result === CmiPayments::PAID ? null : 'failed',
        ]));
    }

    /** CMI sends the browser here after a declined or cancelled payment. */
    public function fail(Request $request, Order $order): RedirectResponse
    {
        $this->payments->apply($order, $request->all()); // records the decline reason if genuine

        return redirect()->route('checkout.confirmation', ['order' => $order->order_number, 'payment' => 'failed']);
    }

    /** CMI's server-to-server notification; the reply body tells CMI what to do. */
    public function callback(Request $request): Response
    {
        return response($this->payments->handleCallback($request->all()), 200)
            ->header('Content-Type', 'text/plain');
    }
}
