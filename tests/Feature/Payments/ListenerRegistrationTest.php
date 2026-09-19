<?php

namespace Tests\Feature\Payments;

use App\Events\PaymentCompleted;
use App\Listeners\FulfillOrder;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class ListenerRegistrationTest extends TestCase
{
    /**
     * FulfillOrder is wired by event discovery ("FulfillOrder@handle").
     * An extra Event::listen() elsewhere would register it a second time
     * (as "FulfillOrder") and run fulfillment twice per paid order.
     */
    public function test_fulfill_order_is_attached_to_payment_completed_exactly_once(): void
    {
        $listeners = Event::getRawListeners()[PaymentCompleted::class] ?? [];

        $fulfillOrder = array_filter(
            $listeners,
            fn ($listener) => match (true) {
                is_string($listener) => strtok($listener, '@') === FulfillOrder::class,
                is_array($listener) => ($listener[0] ?? null) === FulfillOrder::class,
                default => false,
            },
        );

        $this->assertCount(1, $fulfillOrder, 'FulfillOrder must listen to PaymentCompleted exactly once.');
    }
}
