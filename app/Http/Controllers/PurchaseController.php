<?php

namespace App\Http\Controllers;

use App\Domain\Marketplace\ProductFileService;
use App\Models\Download;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * "My purchases": everything a customer has paid for, with the license
 * keys and downloads that FulfillOrder issued. Downloads go through
 * {@see self::download()} only — the file itself is never public.
 */
class PurchaseController extends Controller
{
    public function index(Request $request, ProductFileService $files): Response
    {
        $items = OrderItem::query()
            ->whereHas('order', fn ($q) => $q
                ->where('user_id', $request->user()->id)
                ->where('status', Order::STATUS_PAID))
            ->with(['order:id,order_number,paid_at,created_at', 'product', 'licenses', 'download.orderItem'])
            ->latest('id')
            ->paginate(20);

        $items->through(function (OrderItem $item) use ($files) {
            $download = $item->download;
            $product = $item->product;
            $purchasedAt = $item->order->paid_at ?? $item->order->created_at;
            $supportMonths = (int) ($item->metadata['support_months'] ?? $product?->support_months ?? 0);

            return [
                'id' => $item->id,
                'title' => $item->product_title,
                'type' => $item->product_type,
                'product_url' => $product?->status === 'published' ? route('products.show', $product->slug) : null,
                'thumbnail' => $product?->thumbnail,
                'version' => $product?->version,
                'order_number' => $item->order->order_number,
                'purchased_at' => $purchasedAt?->toIso8601String(),
                'quantity' => $item->quantity,
                'support_until' => $supportMonths > 0 ? $purchasedAt?->copy()->addMonths($supportMonths)->toIso8601String() : null,
                'licenses' => $item->licenses->map(fn ($license) => [
                    'key' => $license->license_key,
                    'tier' => $license->tier,
                    'status' => $license->isRevoked() ? 'revoked' : ($license->isExpired() ? 'expired' : 'active'),
                    'activation_limit' => $license->activation_limit,
                    'activations_used' => count($license->activatedDomains()),
                    'expires_at' => $license->expires_at?->toIso8601String(),
                ])->values(),
                'download' => $download ? [
                    'url' => route('purchases.download', $download),
                    // Regular or Extended License file, by what was bought.
                    'file_name' => $product ? $files->fileInfo($product, $files->slotFor($download))['name'] : null,
                    'file_size' => $product ? $files->fileInfo($product, $files->slotFor($download))['size'] : null,
                    'count' => $download->downloads_count,
                    'max' => $download->max_downloads,
                    'available' => $download->canDownload() && $product !== null && $files->exists($product, $files->slotFor($download)),
                    'reason' => $this->unavailableReason($download, $files),
                ] : null,
            ];
        });

        return Inertia::render('purchases/index', [
            'purchases' => $items,
        ]);
    }

    public function download(Request $request, Download $download, ProductFileService $files): StreamedResponse|RedirectResponse
    {
        // Someone else's grant: behave as if it does not exist.
        abort_unless($download->user_id === $request->user()->id, 404);

        $reason = $this->unavailableReason($download, $files);
        if ($reason !== null) {
            return redirect()->route('purchases.index')->with('error', $reason);
        }

        return $files->deliver($download, $request->ip());
    }

    private function unavailableReason(Download $download, ProductFileService $files): ?string
    {
        if ($download->expires_at && $download->expires_at->isPast()) {
            return __('This download has expired.');
        }

        if (! $download->canDownload()) {
            return __('You have used all :count downloads for this product.', ['count' => $download->max_downloads]);
        }

        if (! $download->product || ! $files->exists($download->product, $files->slotFor($download))) {
            return __('The file isn’t available yet — please try again later or contact support.');
        }

        return null;
    }
}
