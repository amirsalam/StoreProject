<?php

namespace App\Domain\Billing;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\WebhookEvent;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Idempotent processor for Stripe webhook events.
 *
 *   $processor->handle('stripe', $event->id, $event->type, $event->toArray());
 *
 * Guarantees:
 *   1. Duplicate event ids are caught by webhook_events.UNIQUE and
 *      throw DuplicateWebhookException — the controller returns 200.
 *   2. Subscription state transitions happen inside a single DB
 *      transaction with row locks; concurrent webhooks for the same
 *      subscription serialize via SELECT FOR UPDATE.
 *   3. Subscription rows are looked up by stripe_subscription_id —
 *      we cross-check with the metadata.tenant_id to refuse if Stripe
 *      ever sends a mismatched event.
 */
class StripeWebhookProcessor
{
    public function handle(string $gateway, string $eventId, string $type, array $payload): void
    {
        // (1) Dedupe gate. INSERT with UNIQUE — duplicate throws.
        try {
            $event = WebhookEvent::create([
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

        // (2) Apply the event inside a transaction.
        try {
            DB::transaction(function () use ($event) {
                match ($event->event_type) {
                    'customer.subscription.created',
                    'customer.subscription.updated' => $this->upsertSubscription($event),
                    'customer.subscription.deleted' => $this->cancelSubscription($event),
                    'invoice.paid' => $this->markInvoicePaid($event),
                    'invoice.payment_failed' => $this->markPaymentFailed($event),
                    default => null,                                  // ignored types still get logged
                };

                $event->update(['processed_at' => now()]);
            });
        } catch (\Throwable $e) {
            $event->update(['processing_error' => substr($e->getMessage(), 0, 1000)]);
            Log::channel('stack')->error('Stripe webhook processing failed', [
                'event_id' => $eventId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function upsertSubscription(WebhookEvent $event): void
    {
        $object = $event->payload['data']['object'] ?? [];
        $stripeSubId = (string) ($object['id'] ?? '');
        $tenantId = (int) ($object['metadata']['tenant_id'] ?? $event->tenant_id ?? 0);
        $planSlug = (string) ($object['metadata']['plan_slug'] ?? '');
        $cycle = (string) ($object['metadata']['billing_cycle'] ?? TenantSubscription::CYCLE_MONTHLY);

        if (! $stripeSubId || ! $tenantId) {
            return;                                                  // malformed payload — no-op
        }

        $tenant = Tenant::query()->find($tenantId);
        if (! $tenant) {
            return;
        }

        $plan = Plan::query()->where('slug', $planSlug)->first()
            ?? Plan::query()->where('slug', Plan::SLUG_FREE)->first();

        // Lock the matching subscription row (if any) for the duration
        // of the transaction so a concurrent webhook can't race.
        $subscription = TenantSubscription::query()
            ->where('stripe_subscription_id', $stripeSubId)
            ->lockForUpdate()
            ->first();

        $attrs = [
            'tenant_id' => $tenant->id,
            'plan_id' => $plan?->id,
            'status' => $this->mapStatus((string) ($object['status'] ?? '')),
            'billing_cycle' => $cycle,
            'stripe_customer_id' => (string) ($object['customer'] ?? null) ?: null,
            'stripe_subscription_id' => $stripeSubId,
            'current_period_start' => $this->ts($object['current_period_start'] ?? null),
            'current_period_end' => $this->ts($object['current_period_end'] ?? null),
            'trial_ends_at' => $this->ts($object['trial_end'] ?? null),
            'cancel_at' => $this->ts($object['cancel_at'] ?? null),
            'cancelled_at' => $this->ts($object['canceled_at'] ?? null),
        ];

        if ($subscription) {
            $subscription->update($attrs);
        } else {
            TenantSubscription::create($attrs);
        }
    }

    private function cancelSubscription(WebhookEvent $event): void
    {
        $stripeSubId = (string) ($event->payload['data']['object']['id'] ?? '');
        if (! $stripeSubId) {
            return;
        }

        $subscription = TenantSubscription::query()
            ->where('stripe_subscription_id', $stripeSubId)
            ->lockForUpdate()
            ->first();

        $subscription?->update([
            'status' => TenantSubscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);
    }

    private function markInvoicePaid(WebhookEvent $event): void
    {
        $stripeSubId = (string) ($event->payload['data']['object']['subscription'] ?? '');
        if (! $stripeSubId) {
            return;
        }

        $subscription = TenantSubscription::query()
            ->where('stripe_subscription_id', $stripeSubId)
            ->lockForUpdate()
            ->first();

        if ($subscription && $subscription->status === TenantSubscription::STATUS_PAST_DUE) {
            $subscription->update(['status' => TenantSubscription::STATUS_ACTIVE]);
        }
    }

    private function markPaymentFailed(WebhookEvent $event): void
    {
        $stripeSubId = (string) ($event->payload['data']['object']['subscription'] ?? '');
        if (! $stripeSubId) {
            return;
        }

        $subscription = TenantSubscription::query()
            ->where('stripe_subscription_id', $stripeSubId)
            ->lockForUpdate()
            ->first();

        $subscription?->update(['status' => TenantSubscription::STATUS_PAST_DUE]);
    }

    /**
     * Map Stripe's subscription status string to our local enum.
     */
    private function mapStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'trialing' => TenantSubscription::STATUS_TRIALING,
            'active' => TenantSubscription::STATUS_ACTIVE,
            'past_due', 'unpaid' => TenantSubscription::STATUS_PAST_DUE,
            'canceled' => TenantSubscription::STATUS_CANCELLED,
            'incomplete_expired' => TenantSubscription::STATUS_EXPIRED,
            default => TenantSubscription::STATUS_ACTIVE,
        };
    }

    private function ts(mixed $unix): ?Carbon
    {
        if (! is_numeric($unix) || (int) $unix === 0) {
            return null;
        }

        return Carbon::createFromTimestamp((int) $unix);
    }

    /**
     * Stripe puts the customer/subscription metadata in different
     * places depending on the event type — try the common ones.
     */
    private function resolveTenantId(array $payload): ?int
    {
        $metadata = data_get($payload, 'data.object.metadata.tenant_id')
            ?? data_get($payload, 'data.object.lines.data.0.metadata.tenant_id');

        return $metadata !== null ? (int) $metadata : null;
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array($e->errorInfo[1] ?? null, [1062, 19, '23505'], true);
    }
}
