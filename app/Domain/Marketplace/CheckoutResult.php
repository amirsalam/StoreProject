<?php

namespace App\Domain\Marketplace;

use App\Models\Order;

/**
 * Outcome of a successful checkout: the persisted order and the Stripe
 * PaymentIntent client secret the frontend uses to confirm the card
 * (null for fully-discounted $0 orders, which are settled immediately).
 */
class CheckoutResult
{
    public function __construct(
        public readonly Order $order,
        public readonly ?string $clientSecret,
    ) {}
}
