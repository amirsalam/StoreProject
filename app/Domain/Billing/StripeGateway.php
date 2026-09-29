<?php

namespace App\Domain\Billing;

use App\Domain\Marketplace\CheckoutService;
use App\Domain\Payments\StripeCredentials;
use Illuminate\Support\Facades\Log;
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
            $this->client = $this->clientFor($secret);
        }

        return $this->client;
    }

    /**
     * Prove a secret key works with a cheap, read-only call on the resource
     * checkout needs — PaymentIntents — so a restricted key scoped to
     * payments passes too. Throws the Stripe SDK's ApiErrorException
     * subclasses: AuthenticationException for a rejected key,
     * PermissionException for a key without PaymentIntents access. Used by
     * "Test connection"; tests fake it via the container.
     */
    public function verifySecretKey(string $secret): void
    {
        $this->call(fn () => $this->clientFor($secret)->paymentIntents->all(['limit' => 1]));
    }

    /**
     * Run a Stripe SDK call without letting a Stripe *notice* break it.
     *
     * stripe-php surfaces Stripe's advisory `Stripe-Notice` response header
     * (e.g. "You are using an outdated API version") via
     * trigger_error(E_USER_WARNING), which Laravel turns into an exception —
     * after the request already succeeded at Stripe. Log it instead.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function call(callable $callback): mixed
    {
        set_error_handler(function (int $level, string $message): bool {
            Log::warning('Stripe notice: '.$message);

            return true;
        }, E_USER_WARNING);

        try {
            return $callback();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * No explicit stripe_version: the SDK sends the API version it was built
     * for, so request and response shapes always match the installed
     * stripe/stripe-php. (A hard-coded 2024-04-10 pin drew "outdated API
     * version" notices that broke checkout.)
     */
    private function clientFor(string $secret): StripeClient
    {
        return new StripeClient(['api_key' => $secret]);
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
            throw new MissingStripeKeysException('No Stripe gateway is configured for this store.');
        }

        $intent = $this->call(fn () => $this->clientFor($secret)->paymentIntents->create([
            'amount' => $amountCents,
            'currency' => strtolower($currency),
            'metadata' => $metadata,
            'automatic_payment_methods' => ['enabled' => true],
        ]));

        return [
            'id' => $intent->id,
            'client_secret' => $intent->client_secret,
            'customer' => $intent->customer ?? null,
        ];
    }

    /**
     * Fetch a PaymentIntent's current state from the store's Stripe account,
     * for confirm-on-return (the buyer lands back on the confirmation page
     * before — or without — the webhook).
     *
     * @return array{id: string, status: string, object: array<string, mixed>}
     */
    public function retrievePaymentIntent(string $intentId): array
    {
        $secret = app(StripeCredentials::class)->keys()['secret_key'];
        if ($secret === null) {
            throw new MissingStripeKeysException('No Stripe gateway is configured for this store.');
        }

        $intent = $this->call(fn () => $this->clientFor($secret)->paymentIntents->retrieve($intentId));

        return [
            'id' => $intent->id,
            'status' => $intent->status,
            'object' => $intent->toArray(),
        ];
    }
}
