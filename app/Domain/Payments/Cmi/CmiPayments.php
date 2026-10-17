<?php

namespace App\Domain\Payments\Cmi;

use App\Domain\Billing\DuplicateWebhookException;
use App\Domain\Payments\OrderPaymentProcessor;
use App\Models\Order;
use App\Models\Payment;

/**
 * Applies what CMI sends back — the server-to-server callback and the
 * browser's return to okUrl/failUrl. Each is only trusted if its HASH
 * verifies with the store's Store Key, it names this order (oid), and its
 * amount matches the MAD amount recorded at checkout.
 *
 * An approved result goes through OrderPaymentProcessor, the same idempotent
 * path as Stripe: the order is marked paid, fulfilled (license/download) and
 * the confirmation email sent — once, whichever of callback/return comes
 * first. The browser return matters because CMI's server can't reach a
 * development machine's callback URL.
 */
class CmiPayments
{
    public const PAID = 'paid';

    public const FAILED = 'failed';

    public const INVALID = 'invalid';

    public function __construct(
        private readonly CmiGateway $cmi,
        private readonly OrderPaymentProcessor $processor,
    ) {}

    /**
     * The body CMI expects in reply to its callback: ACTION=POSTAUTH to
     * capture an approved pre-authorisation, APPROVED to acknowledge a
     * declined one, FAILURE when the message can't be trusted.
     *
     * @param  array<string, mixed>  $params
     */
    public function handleCallback(array $params): string
    {
        $order = Order::query()->where('order_number', (string) ($params['oid'] ?? ''))->first();

        return match ($order ? $this->apply($order, $params) : self::INVALID) {
            self::PAID => 'ACTION=POSTAUTH',
            self::FAILED => 'APPROVED',
            default => 'FAILURE',
        };
    }

    /**
     * @param  array<string, mixed>  $params
     * @return self::PAID|self::FAILED|self::INVALID
     */
    public function apply(Order $order, array $params): string
    {
        $gateway = $this->cmi->gateway();

        if (! $gateway || ! $this->cmi->verify($params, $gateway) || ($params['oid'] ?? null) !== $order->order_number) {
            return self::INVALID;
        }

        $payment = $order->payments()
            ->where('gateway', 'cmi')
            ->where('gateway_payment_id', $order->order_number)
            ->latest('id')
            ->first();

        if (! $payment) {
            return self::INVALID;
        }

        // Genuine message for an order already paid (CMI retries callbacks,
        // and the browser return may come second): acknowledge it. The
        // amount was checked when it was paid, and the processor has since
        // replaced raw_response with the event payload.
        if ($order->status === Order::STATUS_PAID) {
            return self::PAID;
        }

        $expected = (float) ($payment->raw_response['cmi']['amount_mad'] ?? -1);
        if (abs((float) ($params['amount'] ?? -2) - $expected) > 0.001) {
            report(new \RuntimeException("CMI amount mismatch for {$order->order_number}."));

            return self::INVALID;
        }

        if ((string) ($params['ProcReturnCode'] ?? '') !== CmiGateway::APPROVED) {
            if ($payment->status === Payment::STATUS_PENDING) {
                $payment->update(['failure_reason' => mb_substr((string) ($params['ErrMsg'] ?? $params['Response'] ?? 'Declined'), 0, 255)]);
            }

            return $order->status === Order::STATUS_PAID ? self::PAID : self::FAILED;
        }

        $result = $params;
        unset($result['HASH'], $result['hash']);

        try {
            // Same processor as Stripe webhooks: finds the payment by
            // gateway_payment_id (the oid), marks it and the order paid, and
            // dispatches PaymentCompleted — idempotent on the event id.
            $this->processor->handle('cmi', 'cmi:'.$order->order_number.':'.($params['TransId'] ?? 'approved'), 'payment_intent.succeeded', [
                'type' => 'payment_intent.succeeded',
                'data' => ['object' => [
                    'id' => $order->order_number,
                    'metadata' => ['tenant_id' => (string) $order->tenant_id],
                    'cmi' => $result,
                ]],
            ]);
        } catch (DuplicateWebhookException) {
            // Already applied by the other of callback / browser return.
        }

        return self::PAID;
    }
}
