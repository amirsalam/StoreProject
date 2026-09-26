<?php

namespace App\Domain\Payments;

use App\Models\PaymentGateway;
use App\Tenancy\TenantContext;

/**
 * Stripe keys for marketplace checkout on the current store (tenant).
 *
 * Configured only in Admin → Payment Gateways: the store's active Stripe
 * gateway with both keys (encrypted at rest). There is deliberately no
 * .env fallback — without a configured gateway, checkout shows "payments
 * unavailable".
 *
 * SaaS plan billing (BillingService) charges tenants on the platform's own
 * account and is separate: it still reads config('services.stripe.*').
 */
class StripeCredentials
{
    public function __construct(
        private readonly TenantContext $tenants,
    ) {}

    /**
     * The store's Stripe gateway, if it is active and has both keys.
     * (payment_gateways is unique per tenant + provider: one per store.)
     */
    public function gateway(): ?PaymentGateway
    {
        // Without a tenant the global scope is bypassed in console contexts
        // and could return another store's gateway.
        if (! $this->tenants->hasTenant()) {
            return null;
        }

        $gateway = PaymentGateway::query()
            ->where('provider', 'stripe')
            ->where('is_active', true)
            ->first();

        $configured = $gateway
            && filled($gateway->credentials['publishable_key'] ?? null)
            && filled($gateway->credentials['secret_key'] ?? null);

        return $configured ? $gateway : null;
    }

    /**
     * @return array{publishable_key: ?string, secret_key: ?string}
     */
    public function keys(): array
    {
        $gateway = $this->gateway();

        return [
            'publishable_key' => $gateway?->credentials['publishable_key'],
            'secret_key' => $gateway?->credentials['secret_key'],
        ];
    }

    /**
     * Every webhook signing secret an incoming event may be signed with:
     * the store gateway's (checkout payments), and the platform's .env
     * secret, which signs the SaaS plan-billing events.
     *
     * @return list<string>
     */
    public function webhookSecrets(): array
    {
        return array_values(array_unique(array_filter([
            $this->gateway()?->webhook_secret,
            config('services.stripe.webhook_secret'),
        ], fn ($secret) => is_string($secret) && $secret !== '')));
    }
}
