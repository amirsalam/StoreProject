<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => (string) $request->string('search'),
            'category' => (string) $request->string('category'),
            'type' => (string) $request->string('type'),
            'sort' => (string) $request->string('sort'),
        ];

        $query = Product::query()
            ->with('category:id,name,slug')
            ->listed();

        if ($filters['search'] !== '') {
            $term = '%'.$filters['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('short_description', 'like', $term);
            });
        }

        if ($filters['category'] !== '') {
            $query->whereHas('category', fn ($q) => $q->where('slug', $filters['category']));
        }

        if ($filters['type'] !== '') {
            $query->where('type', $filters['type']);
        }

        match ($filters['sort']) {
            'price_asc' => $query->orderBy('price'),
            'price_desc' => $query->orderByDesc('price'),
            'bestseller' => $query->orderByDesc('sales_count'),
            default => $query->latest(),
        };

        $products = $query->paginate(12)->withQueryString();

        return Inertia::render('products/index', [
            'products' => $products,
            'categories' => Category::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'parent_id']),
            'types' => [
                ['value' => Product::TYPE_DIGITAL_DOWNLOAD, 'label' => 'Digital download'],
                ['value' => Product::TYPE_SUBSCRIPTION, 'label' => 'Subscription'],
                ['value' => Product::TYPE_API_ACCESS, 'label' => 'API access'],
                ['value' => Product::TYPE_LICENSE, 'label' => 'License'],
            ],
            'filters' => $filters,
        ]);
    }

    public function show(Product $product): Response
    {
        $product->load(['category:id,name,slug', 'vendor:id,name,slug,status']);

        abort_unless($product->isListed(), 404);

        $reviews = $product->reviews()
            ->with('user:id,name')
            ->where('is_approved', true)
            ->latest()
            ->limit(10)
            ->get();

        $relatedProducts = Product::query()
            ->listed()
            ->where('id', '!=', $product->id)
            ->when($product->category_id, fn ($q) => $q->where('category_id', $product->category_id))
            ->with('category:id,name,slug')
            ->inRandomOrder()
            ->limit(4)
            ->get();

        return Inertia::render('products/show', [
            'product' => $product,
            'reviews' => $reviews,
            'relatedProducts' => $relatedProducts,
            'averageRating' => round($product->reviews()->where('is_approved', true)->avg('rating') ?? 0, 1),
            'reviewsCount' => $product->reviews()->where('is_approved', true)->count(),
        ]);
    }
}
