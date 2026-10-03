<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitted by FulfillOrder once a paid order's licenses and download grants
 * exist. Listeners that need those artifacts (the order confirmation email)
 * hang off this rather than PaymentCompleted, so they never race
 * fulfillment.
 */
class OrderFulfilled
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Order $order,
    ) {}
}
