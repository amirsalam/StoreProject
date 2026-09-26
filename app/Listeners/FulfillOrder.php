<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Models\Download;
use App\Models\License;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;

/**
 * Digital fulfillment for a paid order.
 *
 * Fans out from {@see PaymentCompleted} (dispatched post-commit by the
 * OrderPaymentProcessor / the $0-order path in CheckoutService): for each
 * line item it issues the right access artifact —
 *   - license / api_access  -> a License (keyed, activation-limited)
 *   - digital_download       -> a Download grant (download-limited)
 *   - subscription           -> skipped (owned by the billing module)
 *
 * Wired by Laravel's event discovery via the type-hinted handle() — do not
 * also register it with Event::listen() (e.g. in AppServiceProvider), or it
 * runs twice per paid order. Guarded by ListenerRegistrationTest.
 *
 * Idempotent: it checks for an existing License/Download on the order item
 * before creating, so a re-dispatched event (Stripe retry, reconciler
 * replay) never double-grants.
 *
 * tenant_id is copied from the order rather than left to BelongsToTenant's
 * auto-fill: the Stripe webhook path runs with no tenant in context, and a
 * license without its store's tenant_id can never be activated through the
 * host-scoped license API.
 */
class FulfillOrder
{
    public function handle(PaymentCompleted $event): void
    {
        $order = $event->order->loadMissing('items.product');

        foreach ($order->items as $item) {
            $product = $item->product;
            if (! $product) {
                continue;
            }

            match ($product->type) {
                Product::TYPE_LICENSE,
                Product::TYPE_API_ACCESS => $this->issueLicense($order, $item, $product),
                Product::TYPE_DIGITAL_DOWNLOAD => $this->grantDownload($order, $item, $product),
                default => null, // subscriptions are handled by the billing module
            };
        }
    }

    private function issueLicense(Order $order, OrderItem $item, Product $product): void
    {
        if ($item->license()->exists()) {
            return; // already fulfilled
        }

        License::create([
            'tenant_id' => $order->tenant_id,
            'user_id' => $order->user_id,
            'product_id' => $product->id,
            'order_item_id' => $item->id,
            'activation_limit' => $product->default_activation_limit ?? 1,
            'activations_count' => 0,
            'status' => License::STATUS_ACTIVE,
        ]);

        $product->increment('sales_count', $item->quantity);
    }

    private function grantDownload(Order $order, OrderItem $item, Product $product): void
    {
        if ($item->download()->exists()) {
            return; // already fulfilled
        }

        Download::create([
            'tenant_id' => $order->tenant_id,
            'user_id' => $order->user_id,
            'product_id' => $product->id,
            'order_item_id' => $item->id,
            'downloads_count' => 0,
            'max_downloads' => $product->download_limit,
        ]);

        $product->increment('sales_count', $item->quantity);
    }
}
