<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Marketplace\ProductFileService;
use App\Domain\Plans\PlanGate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->string('search'),
            'status' => (string) $request->string('status'),
            'type' => (string) $request->string('type'),
        ];

        $query = Product::query()->with('category:id,name,slug');

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(fn ($q) => $q->where('title', 'like', $term)->orWhere('slug', 'like', $term));
        }

        if ($filters['status'] !== '') {
            $query->where('status', $filters['status']);
        }

        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        return Inertia::render('admin/products/index', [
            'products' => $query->latest('id')->paginate(20)->withQueryString(),
            'filters' => $filters,
            'statuses' => $this->statuses(),
            'types' => $this->types(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/products/create', [
            'upload' => app(ProductFileService::class)->uploadOptions(),
            'categories' => $this->categories(),
            'statuses' => $this->statuses(),
            'types' => $this->types(),
        ]);
    }

    /** Form fields handled by ProductFileService rather than mass-assigned. */
    private const FILE_FIELDS = ['download_file', 'remove_download_file', 'download_file_token'];

    public function store(StoreProductRequest $request, PlanGate $gate, ProductFileService $files): RedirectResponse
    {
        $tenant = app(TenantContext::class)->current();

        if ($tenant && ! $gate->withinLimit($tenant, 'products', 1)) {
            // 402 Payment Required is the canonical HTTP code for "upgrade your plan".
            // The Inertia frontend surfaces it as an `errors.plan` validation-shaped error.
            abort(402, 'You\'ve reached your plan\'s product limit. Upgrade to add more.');
        }

        // One transaction: a rejected upload must not leave a file-less product behind.
        $product = DB::transaction(function () use ($request, $files) {
            $product = Product::create($request->safe()->except(self::FILE_FIELDS));
            $files->sync($product, $request->file('download_file'), false, $request->input('download_file_token'), $request->user()->id);

            return $product;
        });

        return redirect()
            ->route('admin.products.index')
            ->with('success', __('Product ":title" created.', ['title' => $product->title]));
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('admin/products/edit', [
            'upload' => app(ProductFileService::class)->uploadOptions(),
            'product' => $product,
            'categories' => $this->categories(),
            'statuses' => $this->statuses(),
            'types' => $this->types(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product, ProductFileService $files): RedirectResponse
    {
        DB::transaction(function () use ($request, $product, $files) {
            $product->update($request->safe()->except(self::FILE_FIELDS));
            $files->sync($product, $request->file('download_file'), $request->boolean('remove_download_file'), $request->input('download_file_token'), $request->user()->id);
        });

        return redirect()
            ->route('admin.products.index')
            ->with('success', __('Product ":title" updated.', ['title' => $product->title]));
    }

    public function destroy(Product $product): RedirectResponse
    {
        $title = $product->title;

        // A sold product is referenced by order lines, licenses, downloads
        // or subscriptions, and deleting it would erase customers' purchase
        // history and access (the database refuses anyway). Archive it
        // instead: hidden from the store, buyers keep what they paid for.
        if ($product->hasSalesHistory()) {
            $product->update(['status' => Product::STATUS_ARCHIVED]);

            return redirect()
                ->route('admin.products.index')
                ->with('success', __('Product ":title" has been sold, so it was archived instead of deleted — it\'s hidden from the store and buyers keep their access.', ['title' => $title]));
        }

        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('success', __('Product ":title" deleted.', ['title' => $title]));
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
     * @return array<int, array{value: string, label: string}>
     */
    private function types(): array
    {
        return [
            ['value' => Product::TYPE_DIGITAL_DOWNLOAD, 'label' => __('Digital download')],
            ['value' => Product::TYPE_SUBSCRIPTION, 'label' => __('Subscription')],
            ['value' => Product::TYPE_API_ACCESS, 'label' => __('API access')],
            ['value' => Product::TYPE_LICENSE, 'label' => __('License')],
        ];
    }

    private function categories()
    {
        return Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id']);
    }
}
