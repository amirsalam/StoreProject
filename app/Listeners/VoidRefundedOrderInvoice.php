<?php

namespace App\Listeners;

use App\Domain\Marketplace\OrderInvoiceService;
use App\Events\PaymentRefunded;

/**
 * Fully refunded order → its invoice is marked void (kept, not deleted,
 * so the numbering has no holes). Partial refunds leave it as is.
 */
class VoidRefundedOrderInvoice
{
    public function __construct(private readonly OrderInvoiceService $invoices) {}

    public function handle(PaymentRefunded $event): void
    {
        try {
            $this->invoices->voidForOrder($event->order->fresh() ?? $event->order);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
