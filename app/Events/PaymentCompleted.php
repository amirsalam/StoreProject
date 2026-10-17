<?php

namespace App\Events;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitted after a one-time payment transitions to succeeded, the order
 * is paid, and the customer wallet is credited — all in one DB tx.
 *
 * Listeners (receipt email, license generation, download access) fan
 * out from here. Each listener is independently retryable.
 */
class PaymentCompleted
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Payment $payment,
        public readonly Order $order,
    ) {}
}
