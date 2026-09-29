<?php

namespace App\Listeners;

use App\Events\OrderFulfilled;
use App\Notifications\OrderConfirmation;
use Illuminate\Support\Facades\Notification;

/**
 * Email the buyer their receipt, license keys and downloads once the order
 * is fulfilled. Addressed to the billing email given at checkout. Sent
 * synchronously (no queue worker needed); a mail failure is logged and
 * never undoes the payment.
 *
 * Wired by event discovery.
 */
class SendOrderConfirmation
{
    public function handle(OrderFulfilled $event): void
    {
        $order = $event->order;

        if (blank($order->billing_email)) {
            return;
        }

        try {
            Notification::route('mail', [$order->billing_email => $order->billing_name])
                ->notify(new OrderConfirmation($order));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
