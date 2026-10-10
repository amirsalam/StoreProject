<?php

namespace App\Console\Commands;

use App\Domain\Marketplace\OrderInvoiceService;
use App\Models\Invoice;
use App\Models\Order;
use Illuminate\Console\Command;

/**
 * Issue invoices for paid orders that don't have one yet — orders paid
 * before automatic invoicing existed, or one whose listener failed.
 *
 *   php artisan invoices:from-orders
 *
 * Safe to run any number of times: an order never gets a second invoice.
 */
class InvoicesFromOrders extends Command
{
    protected $signature = 'invoices:from-orders';

    protected $description = 'Create invoices for paid store orders that do not have one yet.';

    public function handle(OrderInvoiceService $invoices): int
    {
        $created = 0;

        Order::query()
            ->withoutGlobalScope('tenant')
            ->where('status', Order::STATUS_PAID)
            ->whereNotIn('id', Invoice::query()->withoutGlobalScope('tenant')->withTrashed()->whereNotNull('order_id')->select('order_id'))
            ->orderBy('paid_at')
            ->orderBy('id')
            ->each(function (Order $order) use ($invoices, &$created) {
                if ($invoices->createForOrder($order)) {
                    $created++;
                }
            });

        $this->info($created === 1 ? '1 invoice created.' : "{$created} invoices created.");

        return self::SUCCESS;
    }
}
