<?php

namespace App\Domain\Billing;

use App\Domain\Marketplace\CheckoutService;
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
        $intent = $this->client()->paymentIntents->create([
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
