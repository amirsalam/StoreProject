<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Plans\PlanGate;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            'categories' => $this->categories(),
            'statuses' => $this->statuses(),
            'types' => $this->types(),
        ]);
    }

    public function store(StoreProductRequest $request, PlanGate $gate): RedirectResponse
    {
        $tenant = app(TenantContext::class)->current();

        if ($tenant && ! $gate->withinLimit($tenant, 'products', 1)) {
            // 402 Payment Required is the canonical HTTP code for "upgrade your plan".
            // The Inertia frontend surfaces it as an `errors.plan` validation-shaped error.
            abort(402, 'You\'ve reached your plan\'s product limit. Upgrade to add more.');
        }

        $product = Product::create($request->validated());

        return redirect()
            ->route('admin.products.index')
            ->with('success', "Product \"{$product->title}\" created.");
    }

    public function edit(Product $product): Response
    {
        return Inertia::render('admin/products/edit', [
            'product' => $product,
            'categories' => $this->categories(),
            'statuses' => $this->statuses(),
            'types' => $this->types(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $product->update($request->validated());

        return redirect()
            ->route('admin.products.index')
            ->with('success', "Product \"{$product->title}\" updated.");
    }

    public function destroy(Product $product): RedirectResponse
    {
        $title = $product->title;
        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('success', "Product \"{$title}\" deleted.");
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function statuses(): array
    {
        return [
            ['value' => Product::STATUS_DRAFT, 'label' => 'Draft'],
            ['value' => Product::STATUS_PUBLISHED, 'label' => 'Published'],
            ['value' => Product::STATUS_ARCHIVED, 'label' => 'Archived'],
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function types(): array
    {
        return [
            ['value' => Product::TYPE_DIGITAL_DOWNLOAD, 'label' => 'Digital download'],
            ['value' => Product::TYPE_SUBSCRIPTION, 'label' => 'Subscription'],
            ['value' => Product::TYPE_API_ACCESS, 'label' => 'API access'],
            ['value' => Product::TYPE_LICENSE, 'label' => 'License'],
        ];
    }

    private function categories()
    {
        return Category::query()
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'parent_id']);
    }
}
