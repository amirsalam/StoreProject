<?php

namespace App\Domain\Payments;

use App\Domain\Billing\DuplicateWebhookException;
use App\Domain\Billing\MissingStripeKeysException;
use App\Domain\Billing\StripeGateway;
use App\Models\Order;
use App\Models\Payment;
use Stripe\Exception\ApiErrorException;

/**
 * Confirm-on-return: when the buyer lands back on the confirmation page,
 * ask Stripe for the order's PaymentIntent status and, if it succeeded,
 * apply it through OrderPaymentProcessor — marking the order paid and
 * fulfilling it without waiting for the webhook (which may be slow, or not
 * configured at all in local development).
 *
 * Safe alongside the webhook: the processor is idempotent per payment, so
 * whichever arrives second is a no-op. The status comes from Stripe, never
 * from the browser's query string.
 */
class PaymentStatusSync
{
    public function __construct(
        private readonly StripeGateway $gateway,
        private readonly OrderPaymentProcessor $processor,
    ) {}

    public function sync(Order $order): void
    {
        if ($order->status !== Order::STATUS_PENDING) {
            return;
        }

        $payment = $order->payments()
            ->where('gateway', 'stripe')
            ->where('status', Payment::STATUS_PENDING)
            ->whereNotNull('gateway_payment_id')
            ->latest('id')
            ->first();

        if (! $payment) {
            return;
        }

        try {
            $intent = $this->gateway->retrievePaymentIntent($payment->gateway_payment_id);
        } catch (ApiErrorException|MissingStripeKeysException $e) {
            report($e);

            return; // the webhook / reconciler will catch up
        }

        if ($intent['status'] !== 'succeeded') {
            return;
        }

        try {
            $this->processor->handle('stripe', "sync:{$intent['id']}", 'payment_intent.succeeded', [
                'type' => 'payment_intent.succeeded',
                'data' => ['object' => $intent['object']],
            ]);
        } catch (DuplicateWebhookException) {
            // Already synced on an earlier visit.
        } catch (\Throwable $e) {
            // Logged by the processor; never break the confirmation page —
            // the webhook / reconciler will retry.
            report($e);
        }
    }
}
