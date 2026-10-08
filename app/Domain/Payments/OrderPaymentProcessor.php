<?php

namespace App\Domain\Payments;

use App\Domain\Billing\DuplicateWebhookException;
use App\Events\PaymentCompleted;
use App\Events\PaymentRefunded;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use App\Support\Money;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Idempotent processor for one-time payment webhooks
 * (payment_intent.succeeded, charge.refunded, etc.).
 *
 *   $processor->handle('stripe', $event->id, $event->type, $event->toArray());
 *
 * This is the **one-time-payment** counterpart to
 * {@see \App\Domain\Billing\StripeWebhookProcessor} which handles
 * subscription-lifecycle events. They share the webhook_events
 * idempotency table but live in different domain folders to keep
 * concerns separated.
 *
 * Invariants enforced here:
 *   1. The same gateway_event_id is processed at most once — the
 *      INSERT into webhook_events with UNIQUE(gateway_event_id) is
 *      the gate. Duplicates raise DuplicateWebhookException, which
 *      the controller maps to 200 OK.
 *   2. Payment is the source of truth. The order is updated *after*
 *      the payment row commits to `succeeded`.
 *   3. The wallet credit and the ledger entry are atomic with the
 *      Payment/Order transitions — everything in one DB transaction.
 *   4. Concurrent webhooks for the same payment serialize on
 *      SELECT FOR UPDATE.
 */
class OrderPaymentProcessor
{
    /**
     * Event types this processor applies. StripeWebhookController routes
     * exactly these here (and everything else to the billing processor) —
     * both write webhook_events, so an event must go to only one of them.
     */
    public const HANDLED_EVENTS = [
        'payment_intent.succeeded',
        'charge.succeeded',
        'charge.refunded',
        'payment_intent.payment_failed',
        'charge.failed',
    ];

    public function __construct(
        private readonly WalletService $wallets,
    ) {}

    public function handle(string $gateway, string $eventId, string $type, array $payload): void
    {
        // Step 1 — the dedup gate. Insert the event row first; if its
        // UNIQUE constraint trips, we know this event was already
        // delivered. We return without doing anything else.
        try {
            $event = WebhookEvent::query()->create([
                'gateway' => $gateway,
                'gateway_event_id' => $eventId,
                'event_type' => $type,
                'tenant_id' => $this->resolveTenantId($payload),
                'payload' => $payload,
                'received_at' => now(),
            ]);
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new DuplicateWebhookException("Already processed event {$eventId}");
            }
            throw $e;
        }

        // Step 2 — apply the event inside a DB transaction. Anything
        // that throws here rolls everything back, and the webhook_event
        // row gets its processing_error stamped so the reconciler can
        // re-run it.
        try {
            $emit = DB::transaction(function () use ($event) {
                return match ($event->event_type) {
                    'payment_intent.succeeded',
                    'charge.succeeded' => $this->applySuccess($event),
                    'charge.refunded' => $this->applyRefund($event),
                    'payment_intent.payment_failed',
                    'charge.failed' => $this->applyFailure($event),
                    default => null,                    // ignored types still get logged in webhook_events
                };
            });

            $event->update(['processed_at' => now()]);

            // Events are dispatched *after* the transaction commits so
            // listeners can rely on the new state being visible to other
            // connections / read replicas.
            if ($emit instanceof PaymentCompleted) {
                PaymentCompleted::dispatch($emit->payment, $emit->order);
            } elseif ($emit instanceof PaymentRefunded) {
                PaymentRefunded::dispatch($emit->payment, $emit->order, $emit->amountCents);
            }
        } catch (\Throwable $e) {
            $event->update(['processing_error' => mb_substr($e->getMessage(), 0, 1000)]);
            Log::channel('stack')->error('Order payment webhook failed', [
                'event_id' => $eventId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Apply a payment_intent.succeeded / charge.succeeded.
     * Returns the event to dispatch (post-commit), or null if no-op.
     */
    private function applySuccess(WebhookEvent $event): ?PaymentCompleted
    {
        $object = $event->payload['data']['object'] ?? [];
        $gatewayPaymentId = (string) ($object['id'] ?? '');
        if ($gatewayPaymentId === '') {
            return null;
        }

        // Lock the matching payment row. If it doesn't exist yet (rare:
        // webhook arrives before our POST /checkout finishes), abort and
        // rely on Stripe's retry — the next attempt will find the row.
        $payment = Payment::query()
            ->where('gateway_payment_id', $gatewayPaymentId)
            ->lockForUpdate()
            ->first();

        if (! $payment) {
            throw new \RuntimeException(
                "No local payment for {$gatewayPaymentId}; will retry."
            );
        }

        // If we already processed this payment to succeeded, there's
        // nothing else to do. (The webhook_events UNIQUE gate would
        // already have caught a true duplicate event id; this guards
        // against two *different* events for the same payment, e.g.
        // payment_intent.succeeded + charge.succeeded.)
        if ($payment->status === Payment::STATUS_SUCCEEDED) {
            return null;
        }

        $payment->update([
            'status' => Payment::STATUS_SUCCEEDED,
            'processed_at' => now(),
            'raw_response' => $event->payload,
        ]);

        // Payment row is now the source of truth: lock + update the
        // order to follow it.
        $order = Order::query()
            ->whereKey($payment->order_id)
            ->lockForUpdate()
            ->first();

        if ($order && $order->status !== Order::STATUS_PAID) {
            $order->update([
                'status' => Order::STATUS_PAID,
                'paid_at' => now(),
            ]);
        }

        // Credit the customer wallet. The idempotency_key is built
        // deterministically from the payment id, so retries (or the
        // reconciler replaying this event) credit at most once.
        $user = User::query()->find($payment->user_id);
        if ($user) {
            $wallet = $this->wallets->forUser($user, $payment->currency, $payment->tenant_id);
            $this->wallets->credit(
                wallet: $wallet,
                cents: Money::toCents((string) $payment->amount),
                idempotencyKey: "payment:{$payment->id}:credit",
                payment: $payment,
                order: $order,
                reference: "order:{$order?->id}",
                metadata: ['gateway_payment_id' => $gatewayPaymentId],
            );
        }

        return $order ? new PaymentCompleted($payment, $order) : null;
    }

    private function applyRefund(WebhookEvent $event): ?PaymentRefunded
    {
        $object = $event->payload['data']['object'] ?? [];
        $gatewayPaymentId = (string) ($object['payment_intent'] ?? $object['id'] ?? '');
        $refundedCents = (int) ($object['amount_refunded'] ?? $object['amount'] ?? 0);

        if ($gatewayPaymentId === '' || $refundedCents <= 0) {
            return null;
        }

        $payment = Payment::query()
            ->where('gateway_payment_id', $gatewayPaymentId)
            ->lockForUpdate()
            ->first();

        if (! $payment) {
            return null;
        }

        $payment->update(['status' => Payment::STATUS_REFUNDED]);

        $order = Order::query()
            ->whereKey($payment->order_id)
            ->lockForUpdate()
            ->first();

        if ($order && $order->status !== Order::STATUS_REFUNDED) {
            $order->update([
                'status' => Order::STATUS_REFUNDED,
                'refunded_at' => now(),
            ]);
        }

        $user = User::query()->find($payment->user_id);
        if ($user) {
            $wallet = $this->wallets->forUser($user, $payment->currency, $payment->tenant_id);
            $this->wallets->debit(
                wallet: $wallet,
                cents: $refundedCents,
                idempotencyKey: "payment:{$payment->id}:refund:{$event->id}",
                payment: $payment,
                order: $order,
                reference: "refund:{$gatewayPaymentId}",
            );
        }

        return $order ? new PaymentRefunded($payment, $order, $refundedCents) : null;
    }

    private function applyFailure(WebhookEvent $event): null
    {
        $gatewayPaymentId = (string) ($event->payload['data']['object']['id'] ?? '');
        if ($gatewayPaymentId === '') {
            return null;
        }

        $payment = Payment::query()
            ->where('gateway_payment_id', $gatewayPaymentId)
            ->lockForUpdate()
            ->first();

        $payment?->update([
            'status' => Payment::STATUS_FAILED,
            'failure_reason' => $event->payload['data']['object']['last_payment_error']['message'] ?? null,
        ]);

        // Note: orders are *not* marked failed on the first payment
        // failure — a customer can retry. The order stays pending until
        // the user explicitly cancels or the reconciler ages it out.

        return null;
    }

    private function resolveTenantId(array $payload): ?int
    {
        $metadata = data_get($payload, 'data.object.metadata.tenant_id');
        return $metadata !== null ? (int) $metadata : null;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array($e->errorInfo[1] ?? null, [1062, 19, '23505'], true);
    }
}
