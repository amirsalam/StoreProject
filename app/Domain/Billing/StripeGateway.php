<?php

namespace App\Domain\Billing;

use App\Domain\Marketplace\CheckoutService;
use App\Domain\Payments\StripeCredentials;
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
     * Prove a secret key works with a cheap, read-only call (the account
     * balance). Throws the Stripe SDK's ApiErrorException subclasses —
     * AuthenticationException for a rejected key. Used by the admin
     * "Test connection" button; tests fake it via the container.
     */
    public function verifySecretKey(string $secret): void
    {
        $this->clientFor($secret)->balance->retrieve();
    }

    private function clientFor(string $secret): StripeClient
    {
        return new StripeClient(['api_key' => $secret, 'stripe_version' => '2024-04-10']);
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

    /**
     * Create a one-time PaymentIntent for a checkout order.
     *
     * Returns the minimal shape {@see CheckoutService}
     * persists onto the local Payment row:
     *   ['id' => 'pi_...', 'client_secret' => '...', 'customer' => 'cus_...'|null]
     *
     * Kept here (not in the service) so the Stripe SDK call sits behind
     * the gateway seam — tests bind a fake StripeGateway via the
     * container and never touch the live API, exactly like the
     * subscription-billing path does.
     *
     * @param  array<string, string>  $metadata
     * @return array{id: string, client_secret: string|null, customer: string|null}
     */
    public function createPaymentIntent(int $amountCents, string $currency, array $metadata = []): array
    {
        // Checkout charges on the store's own Stripe account (Admin → Payment
        // Gateways) — not the platform billing client.
        $secret = app(StripeCredentials::class)->keys()['secret_key'];
        if ($secret === null) {
            throw new \RuntimeException('No Stripe gateway is configured for this store.');
        }

        $intent = $this->clientFor($secret)->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => strtolower($currency),
            'metadata' => $metadata,
            'automatic_payment_methods' => ['enabled' => true],
        ]);

        return [
            'id' => $intent->id,
            'client_secret' => $intent->client_secret,
            'customer' => $intent->customer ?? null,
        ];
    }
}
