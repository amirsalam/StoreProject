<?php

namespace App\Domain\Marketplace;

use App\Models\Invoice;
use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Money;
use Illuminate\Database\QueryException;

/**
 * One invoice per paid store order, in the order's workspace (Workspace →
 * Invoices) and downloadable by the buyer from "My purchases".
 *
 * Idempotent — `invoices.order_id` is unique and checked first — so a
 * replayed payment event, the backfill command and a race between the
 * Stripe webhook and the return URL all end with exactly one invoice.
 * Every lookup is scoped to the order's tenant explicitly: payment
 * webhooks run with no tenant in context.
 */
class OrderInvoiceService
{
    public function forOrder(Order $order): ?Invoice
    {
        return Invoice::query()->forTenant($order->tenant_id)->where('order_id', $order->id)->first();
    }

    public function createForOrder(Order $order): ?Invoice
    {
        if ($order->status !== Order::STATUS_PAID) {
            return null;
        }

        if ($existing = $this->forOrder($order)) {
            return $existing;
        }

        $order->loadMissing('items');
        $currency = strtoupper((string) ($order->currency ?: 'USD'));
        $minor = fn ($amount) => Money::toMinor((string) $amount, $currency);
        $paidAt = $order->paid_at ?? now();

        // The number can be taken by a concurrent writer; retry with the next.
        for ($attempt = 1; ; $attempt++) {
            try {
                return Invoice::create([
                    'tenant_id' => $order->tenant_id,
                    'client_id' => $order->user_id,
                    'order_id' => $order->id,
                    'number' => Invoice::nextNumber($order->tenant_id),
                    'status' => Invoice::STATUS_PAID,
                    'subtotal_cents' => $minor($order->subtotal),
                    'discount_cents' => $minor($order->discount ?? 0),
                    'tax_cents' => $minor($order->tax ?? 0),
                    'total_cents' => $minor($order->total),
                    'currency' => $currency,
                    'line_items' => $order->items->map(fn (OrderItem $item) => [
                        'description' => $item->product_title,
                        'license' => $item->metadata['license'] ?? null,
                        'qty' => (int) $item->quantity,
                        'unit_cents' => $minor($item->unit_price),
                        'total_cents' => $minor($item->total_price),
                    ])->values()->all(),
                    'notes' => $order->order_number,
                    'issued_on' => $paidAt->toDateString(),
                    'due_on' => $paidAt->toDateString(),
                    'sent_at' => $paidAt,
                    'paid_at' => $paidAt,
                ]);
            } catch (QueryException $e) {
                // Another request issued this order's invoice meanwhile.
                if ($existing = $this->forOrder($order)) {
                    return $existing;
                }
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    /**
     * A fully refunded order: its invoice no longer stands.
     */
    public function voidForOrder(Order $order): void
    {
        $invoice = $this->forOrder($order);
        if ($invoice && $order->status === Order::STATUS_REFUNDED && $invoice->status !== Invoice::STATUS_VOID) {
            $invoice->update(['status' => Invoice::STATUS_VOID]);
        }
    }
}
