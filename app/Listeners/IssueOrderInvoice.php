<?php

namespace App\Listeners;

use App\Domain\Marketplace\OrderInvoiceService;
use App\Events\PaymentCompleted;

/**
 * Paid order → invoice (Workspace → Invoices, and a receipt the buyer can
 * open from "My purchases"). Wired by event discovery like FulfillOrder.
 *
 * Never lets an invoicing problem break payment handling: the buyer has
 * paid either way, and `php artisan invoices:from-orders` fills any gap.
 */
class IssueOrderInvoice
{
    public function __construct(private readonly OrderInvoiceService $invoices) {}

    public function handle(PaymentCompleted $event): void
    {
        try {
            $this->invoices->createForOrder($event->order->fresh() ?? $event->order);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
