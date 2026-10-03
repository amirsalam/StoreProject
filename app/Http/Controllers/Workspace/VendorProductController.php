<?php

namespace App\Http\Controllers\Workspace;

use App\Domain\Marketplace\ProductFileService;
use App\Domain\Plans\PlanGate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspace\StoreVendorProductRequest;
use App\Http\Requests\Workspace\UpdateVendorProductRequest;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Vendor;
use App\Services\LicensingSettings;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A seller manages the products of their own store (and sees their sales).
 * Everything is scoped to products.vendor_id = the user's vendor; another
 * seller's product answers 404. Sellers can't feature products — that
 * stays an admin decision.
 */
class VendorProductController extends Controller
{
    /** Form fields that are not mass-assigned onto the product. */
    private const EXCLUDED = ['download_file', 'remove_download_file', 'download_file_token', 'is_featured'];

    public function index(Request $request, ProductFileService $files): Response
    {
        $vendor = $this->vendor($request);

        $filters = [
            'search' => (string) $request->string('search'),
            'status' => (string) $request->string('status'),
        ];

        $products = null;
        if ($vendor) {
            $query = Product::query()->where('vendor_id', $vendor->id)->with('category:id,name');

            if ($filters['search'] !== '') {
                $term = '%'.$filters['search'].'%';
                $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('slug', 'like', $term));
            }
            if ($filters['status'] !== '') {
                $query->where('status', $filters['status']);
            }

            $products = $query->latest('id')->paginate(20)->withQueryString()
                ->through(fn (Product $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'slug' => $p->slug,
                    'type' => $p->type,
                    'status' => $p->status,
                    'price' => $p->price,
                    'sale_price' => $p->sale_price,
                    'currency' => $p->currency,
                    'sales_count' => $p->sales_count,
                    'category' => $p->category?->name,
                    // On disk, not just recorded: demo data carries paths without files.
                    'has_file' => $files->exists($p),
                    'url' => $p->status === Product::STATUS_PUBLISHED && $vendor->isActive() ? route('products.show', $p->slug) : null,
                ]);
        }

        return Inertia::render('workspace/products/index', [
            'vendor' => $vendor?->only(['id', 'name', 'slug', 'status']),
            'products' => $products,
            'filters' => $filters,
            'statuses' => $this->statuses(),
            'types' => $this->types(),
        ]);
    }

    public function create(Request $request): Response|RedirectResponse
    {
        if (! $this->vendor($request)) {
            return $this->openStoreFirst();
        }

        return Inertia::render('workspace/products/create', [
            'currencies' => array_keys(config('currencies')),
            'licensing' => app(LicensingSettings::class)->forForm(),
            'upload' => app(ProductFileService::class)->uploadOptions(),
            'categories' => $this->categories(),
            'statuses' => $this->statuses(),
            'types' => $this->types(),
        ]);
    }

    public function store(StoreVendorProductRequest $request, PlanGate $gate, ProductFileService $files): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $tenant = app(TenantContext::class)->current();

        if ($tenant && ! $gate->withinLimit($tenant, 'products', 1)) {
            abort(402, 'You\'ve reached your plan\'s product limit. Upgrade to add more.');
        }

        // One transaction: a rejected upload must not leave a file-less product behind.
        $product = DB::transaction(function () use ($request, $vendor, $files) {
            $product = Product::create([
                ...$request->safe()->except(self::EXCLUDED),
                'vendor_id' => $vendor->id,
                'is_featured' => false,
            ]);
            $files->sync($product, $request->file('download_file'), false, $request->input('download_file_token'), $request->user()->id);

            return $product;
        });

        return redirect()
            ->route('workspace.products.index')
            ->with('success', __('Product ":title" created.', ['title' => $product->title]));
    }

    public function edit(Request $request, Product $product): Response|RedirectResponse
    {
        $this->authorizeOwnership($request, $product);

        return Inertia::render('workspace/products/edit', [
            'currencies' => array_keys(config('currencies')),
            'licensing' => app(LicensingSettings::class)->forForm(),
            'upload' => app(ProductFileService::class)->uploadOptions(),
            'product' => $product,
            'categories' => $this->categories(),
            'statuses' => $this->statuses(),
            'types' => $this->types(),
        ]);
    }

    public function update(UpdateVendorProductRequest $request, Product $product, ProductFileService $files): RedirectResponse
    {
        $this->authorizeOwnership($request, $product);

        DB::transaction(function () use ($request, $product, $files) {
            $product->update($request->safe()->except(self::EXCLUDED));
            $files->sync($product, $request->file('download_file'), $request->boolean('remove_download_file'), $request->input('download_file_token'), $request->user()->id);
        });

        return redirect()
            ->route('workspace.products.index')
            ->with('success', __('Product ":title" updated.', ['title' => $product->title]));
    }

    public function destroy(Request $request, Product $product, ProductFileService $files): RedirectResponse
    {
        $this->authorizeOwnership($request, $product);
        $title = $product->title;

        // Sold products are archived, never deleted: buyers keep their
        // license keys and downloads (same rule as the admin catalog).
        if ($product->hasSalesHistory()) {
            $product->update(['status' => Product::STATUS_ARCHIVED]);

            return redirect()
                ->route('workspace.products.index')
                ->with('success', __('Product ":title" has been sold, so it was archived instead of deleted — it\'s hidden from the store and buyers keep their access.', ['title' => $title]));
        }

        $files->remove($product);
        $product->delete();

        return redirect()
            ->route('workspace.products.index')
            ->with('success', __('Product ":title" deleted.', ['title' => $title]));
    }

    /**
     * Paid order lines for this seller's products — only their own sales.
     */
    public function sales(Request $request): Response
    {
        $vendor = $this->vendor($request);

        $sales = null;
        $summary = ['revenue' => '0.00', 'orders' => 0, 'units' => 0];

        if ($vendor) {
            $base = OrderItem::query()
                ->whereHas('product', fn ($q) => $q->where('vendor_id', $vendor->id))
                ->whereHas('order', fn ($q) => $q->where('status', Order::STATUS_PAID));

            $summary = [
                'revenue' => number_format((float) (clone $base)->sum('total_price'), 2, '.', ''),
                'orders' => (clone $base)->distinct()->count('order_id'),
                'units' => (int) (clone $base)->sum('quantity'),
            ];

            $sales = (clone $base)
                ->with('order:id,order_number,billing_name,currency,paid_at,created_at')
                ->latest('id')
                ->paginate(25)
                ->through(fn (OrderItem $item) => [
                    'id' => $item->id,
                    'order_number' => $item->order->order_number,
                    'product' => $item->product_title,
                    'quantity' => $item->quantity,
                    'total' => $item->total_price,
                    'currency' => $item->order->currency ?? 'USD',
                    'customer' => $item->order->billing_name,
                    'date' => ($item->order->paid_at ?? $item->order->created_at)?->toIso8601String(),
                ]);
        }

        return Inertia::render('workspace/sales/index', [
            'vendor' => $vendor?->only(['id', 'name', 'slug']),
            'sales' => $sales,
            'summary' => $summary,
        ]);
    }

    private function vendor(Request $request): ?Vendor
    {
        return $request->user()->vendor()->first();
    }

    private function authorizeOwnership(Request $request, Product $product): void
    {
        $vendor = $this->vendor($request);

        abort_unless($vendor && $product->vendor_id === $vendor->id, 404);
    }

    private function openStoreFirst(): RedirectResponse
    {
        return redirect()
            ->route('workspace.vendor.edit')
            ->with('error', __('Open your store first — then you can add products to it.'));
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return [
            ['value' => Product::STATUS_DRAFT, 'label' => __('Draft')],
            ['value' => Product::STATUS_PUBLISHED, 'label' => __('Published')],
            ['value' => Product::STATUS_ARCHIVED, 'label' => __('Archived')],
        ];
    }

    /**
     * Sellers sell one-off digital goods; subscriptions belong to the
     * platform's billing module.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function types(): array
    {
        return [
            ['value' => Product::TYPE_DIGITAL_DOWNLOAD, 'label' => __('Digital download')],
            ['value' => Product::TYPE_LICENSE, 'label' => __('License')],
            ['value' => Product::TYPE_API_ACCESS, 'label' => __('API access')],
        ];
    }

    private function categories()
    {
        return Category::query()->orderBy('name')->get(['id', 'name', 'slug', 'parent_id']);
    }
}
