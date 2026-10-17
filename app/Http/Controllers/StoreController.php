<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Review;
use App\Models\Vendor;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Public vendor storefront at /store/{vendor:slug}. Only active vendors
 * are visible; the page shows the vendor profile + their published
 * catalog + an aggregate rating. See docs/marketplace-architecture.md §2.
 */
class StoreController extends Controller
{
    public function show(Request $request, Vendor $vendor): Response
    {
        abort_unless($vendor->isActive(), 404);

        $vendor->load('profile');

        $products = $vendor->products()
            ->where('status', Product::STATUS_PUBLISHED)
            ->with('category:id,name,slug')
            ->latest('id')
            ->paginate(12)
            ->withQueryString();

        // Aggregate rating across approved reviews of this vendor's products.
        $productIds = $vendor->products()->pluck('id');
        $ratingQuery = Review::query()
            ->whereIn('product_id', $productIds)
            ->where('is_approved', true);

        return Inertia::render('store/show', [
            'vendor' => [
                'id' => $vendor->id,
                'name' => $vendor->name,
                'slug' => $vendor->slug,
                'is_verified' => $vendor->isVerified(),
                'profile' => $vendor->profile,
            ],
            'products' => $products,
            'averageRating' => round((float) $ratingQuery->avg('rating'), 1),
            'reviewsCount' => (clone $ratingQuery)->count(),
            'productsCount' => $vendor->products()->where('status', Product::STATUS_PUBLISHED)->count(),
        ]);
    }
}
