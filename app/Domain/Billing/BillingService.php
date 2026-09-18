<?php

namespace App\Domain\Billing;

use App\Models\Plan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use Illuminate\Support\Facades\DB;

/**
 * Tenant SaaS billing operations.
 *
 *   $billing->ensureCustomer($tenant);
 *   $billing->checkoutSession($tenant, Plan::SLUG_PRO, 'monthly');
 *   $billing->portalSession($tenant, $returnUrl);
 *   $billing->cancelAtPeriodEnd($tenant);
 *
 * State changes that originate from Stripe (subscription created,
 * invoice paid, payment failed, subscription deleted) are NOT
 * applied here — they're applied by StripeWebhookProcessor when
 * Stripe notifies us, so the webhook handler stays the single
 * source of truth for tenant_subscriptions row state.
 */
class BillingService
{
    public function __construct(private readonly StripeGateway $stripe) {}

    /**
     * Idempotent: creates a Stripe Customer if the tenant doesn't have
     * one yet, and caches the customer id on the tenant row.
     */
    public function ensureCustomer(Tenant $tenant): string
    {
        if ($tenant->stripe_customer_id) {
            return $tenant->stripe_customer_id;
        }

        $customer = $this->stripe->client()->customers->create([
            'name' => $tenant->name,
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
                'tenant_slug' => $tenant->slug,
            ],
        ]);

        $tenant->update(['stripe_customer_id' => $customer->id]);

        return $customer->id;
    }

    /**
     * Create a Stripe Checkout session for upgrading to a paid plan.
     *
     * Returns the session URL — the caller redirects the browser
     * there. On completion, Stripe POSTs the
     * checkout.session.completed event to our webhook, which
     * triggers the subscription create flow.
     */
    public function checkoutSession(Tenant $tenant, string $planSlug, string $cycle, string $successUrl, string $cancelUrl): string
    {
        $price = $this->stripe->priceFor($planSlug, $cycle);
        if (! $price) {
            throw new \InvalidArgumentException("No Stripe price configured for {$planSlug}/{$cycle}.");
        }

        $session = $this->stripe->client()->checkout->sessions->create([
            'mode' => 'subscription',
            'customer' => $this->ensureCustomer($tenant),
            'line_items' => [[
                'price' => $price,
                'quantity' => 1,
            ]],
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'subscription_data' => [
                'metadata' => [
                    'tenant_id' => (string) $tenant->id,
                    'plan_slug' => $planSlug,
                    'billing_cycle' => $cycle,
                ],
            ],
            'metadata' => [
                'tenant_id' => (string) $tenant->id,
            ],
            'allow_promotion_codes' => true,
        ]);

        return (string) $session->url;
    }

    /**
     * Create a Stripe Customer Portal session — Stripe-hosted UI for
     * the tenant to update cards, view invoices, cancel, upgrade.
     */
    public function portalSession(Tenant $tenant, string $returnUrl): string
    {
        $session = $this->stripe->client()->billingPortal->sessions->create([
            'customer' => $this->ensureCustomer($tenant),
            'return_url' => $returnUrl,
        ]);

        return (string) $session->url;
    }

    /**
     * Mark a subscription for cancellation at period end. Stripe will
     * fire customer.subscription.updated immediately and
     * customer.subscription.deleted at period close — both handled
     * by the webhook processor.
     */
    public function cancelAtPeriodEnd(Tenant $tenant): void
    {
        $subscription = $tenant->currentSubscription;
        if (! $subscription || ! $subscription->stripe_subscription_id) {
            return;
        }

        $this->stripe->client()->subscriptions->update($subscription->stripe_subscription_id, [
            'cancel_at_period_end' => true,
        ]);

        // Mirror the intent locally so the UI updates immediately; the
        // webhook will confirm by stamping cancel_at on the row.
        $subscription->update(['cancel_at' => $subscription->current_period_end]);
    }

    /**
     * Local-only attach: assign a plan to a tenant without going
     * through Stripe. Used for free-tier provisioning and the seeder.
     */
    public function attachFreePlan(Tenant $tenant): TenantSubscription
    {
        $free = Plan::query()->where('slug', Plan::SLUG_FREE)->firstOrFail();

        return DB::transaction(function () use ($tenant, $free) {
            $existing = $tenant->currentSubscription;
            if ($existing) {
                return $existing;
            }

            return TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $free->id,
                'status' => TenantSubscription::STATUS_ACTIVE,
                'billing_cycle' => TenantSubscription::CYCLE_MONTHLY,
                'current_period_start' => now(),
                'current_period_end' => now()->addYears(10), // free = open-ended
            ]);
        });
    }
}
