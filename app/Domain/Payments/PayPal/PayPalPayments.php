<?php

namespace App\Domain\Payments\PayPal;

use App\Domain\Billing\DuplicateWebhookException;
use App\Domain\Payments\OrderPaymentProcessor;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Services\BrandingService;
use Illuminate\Http\Client\RequestException;

/**
 * PayPal for our orders: start a payment (PayPal order + approval link)
 * and complete it when the buyer returns (capture, then verify).
 *
 * A capture only counts if PayPal reports it COMPLETED for exactly this
 * order's total and currency, under this order's number. It then goes
 * through OrderPaymentProcessor — the same idempotent path as Stripe and
 * CMI: order marked paid, fulfilled (license / download), email sent, once.
 */
class PayPalPayments
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const INVALID = 'invalid';

    public function __construct(
        private readonly PayPalGateway $paypal,
        private readonly OrderPaymentProcessor $processor,
        private readonly BrandingService $branding,
    ) {}

    /**
     * Create the PayPal order for our pending order and remember it on the
     * payment. Returns the URL to send the buyer to.
     *
     * @throws RequestException
     */
    public function start(Order $order, Payment $payment, PaymentGateway $gateway): string
    {
        $created = $this->paypal->createOrder(
            $gateway,
            $order,
            route('checkout.paypal.return', $order->order_number),
            route('checkout.paypal.cancel', $order->order_number),
            (string) $this->branding->summary()['title'],
        );

        $payment->update([
            'gateway_payment_id' => $created['id'],
            'raw_response' => ['paypal' => ['order_id' => $created['id'], 'approve_url' => $created['approve_url']]],
        ]);

        return $created['approve_url'];
    }

    /**
     * Capture and verify after the buyer approved on PayPal.
     *
     * @return self::PAID|self::FAILED|self::INVALID
     */
    public function complete(Order $order, ?string $returnedToken = null): string
    {
        if ($order->status === Order::STATUS_PAID) {
            return self::PAID;
        }

        $gateway = $this->paypal->gateway();
        $payment = $order->payments()->where('gateway', 'paypal')->latest('id')->first();

        if (! $gateway || ! $payment || blank($payment->gateway_payment_id)) {
            return self::INVALID;
        }

        // The token PayPal appends to the return URL is its order id.
        if ($returnedToken !== null && $returnedToken !== $payment->gateway_payment_id) {
            return self::INVALID;
        }

        try {
            $result = $this->paypal->capture($gateway, $payment->gateway_payment_id);
        } catch (RequestException $e) {
            $issue = $e->response->json('details.0.issue') ?? $e->response->json('name');

            if ($issue === 'ORDER_ALREADY_CAPTURED') {
                $result = $this->paypal->getOrder($gateway, $payment->gateway_payment_id);
            } else {
                $payment->update(['failure_reason' => mb_substr((string) ($e->response->json('details.0.description') ?? $issue ?? 'PayPal error'), 0, 255)]);

                return self::FAILED;
            }
        }

        $unit = $result['purchase_units'][0] ?? [];
        $capture = $unit['payments']['captures'][0] ?? [];
        $amount = $capture['amount'] ?? [];

        $ours = in_array($order->order_number, [$unit['reference_id'] ?? null, $unit['custom_id'] ?? null, $capture['custom_id'] ?? null], true);
        $exact = ($amount['value'] ?? null) === PayPalGateway::amount($order->total, $order->currency)
            && strtoupper((string) ($amount['currency_code'] ?? '')) === strtoupper($order->currency);

        if (! $ours || ! $exact) {
            report(new \RuntimeException("PayPal capture does not match order {$order->order_number}."));

            return self::INVALID;
        }

        if (($result['status'] ?? null) !== 'COMPLETED' || ($capture['status'] ?? null) !== 'COMPLETED') {
            // PENDING (e.g. eCheck / review) is not money yet.
            $payment->update(['failure_reason' => mb_substr('PayPal capture '.($capture['status'] ?? $result['status'] ?? 'unknown'), 0, 255)]);

            return self::FAILED;
        }

        try {
            $this->processor->handle('paypal', 'paypal:'.($capture['id'] ?? $payment->gateway_payment_id), 'payment_intent.succeeded', [
                'type' => 'payment_intent.succeeded',
                'data' => ['object' => [
                    'id' => $payment->gateway_payment_id,
                    'metadata' => ['tenant_id' => (string) $order->tenant_id],
                    'paypal' => ['order_id' => $result['id'] ?? null, 'capture_id' => $capture['id'] ?? null, 'payer' => $result['payer']['email_address'] ?? null],
                ]],
            ]);
        } catch (DuplicateWebhookException) {
            // Already applied (double click on the return page).
        }

        return self::PAID;
    }
}
