<?php

namespace App\Domain\Billing;

use Stripe\StripeClient;

/**
 * Thin wrapper around the Stripe SDK so we never construct the
 * client by hand. Lets us swap in a fake during tests via the
 * container without touching call sites.
 */
class StripeGateway
{
    private ?StripeClient $client = null;

    public function client(): StripeClient
    {
        if ($this->client === null) {
            $secret = (string) config('services.stripe.secret', '');
            if ($secret === '') {
                throw new \RuntimeException('STRIPE_SECRET is not configured.');
            }
            $this->client = new StripeClient([
                'api_key' => $secret,
                'stripe_version' => '2024-04-10',
            ]);
        }

        return $this->client;
    }

    /**
     * Look up the Stripe price id configured for a plan-slug+cycle
     * combo. Returns null if the price isn't configured (e.g. a free
     * tier that doesn't bill, or enterprise quotes that go through
     * sales not self-serve).
     */
    public function priceFor(string $planSlug, string $cycle): ?string
    {
        return config("services.stripe.price_ids.{$planSlug}_{$cycle}");
    }
}
