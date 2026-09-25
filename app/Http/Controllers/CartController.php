<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CartService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CartController extends Controller
{
    public function __construct(private readonly CartService $cart) {}

    public function show(): Response
    {
        $items = $this->cart->lineItems()->map(fn ($row) => [
            'product' => [
                'id' => $row['product']->id,
                'title' => $row['product']->title,
                'slug' => $row['product']->slug,
                'type' => $row['product']->type,
                'thumbnail' => $row['product']->thumbnail,
                'price' => (string) $row['product']->price,
                'sale_price' => $row['product']->sale_price !== null ? (string) $row['product']->sale_price : null,
            ],
            'quantity' => $row['quantity'],
            'unit_price' => $row['unit_price'],
            'line_total' => $row['line_total'],
        ]);

        return Inertia::render('cart/index', [
            'items' => $items,
            'subtotal' => $this->cart->subtotal(),
            'currency' => 'USD',
        ]);
    }

    public function add(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        $product = Product::query()
            ->where('id', $data['product_id'])
            ->where('status', Product::STATUS_PUBLISHED)
            ->firstOrFail();

        $this->cart->add($product, $data['quantity'] ?? 1);

        return back()->with('success', "Added \"{$product->title}\" to your cart.");
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:99'],
        ]);

        $this->cart->update($product, $data['quantity']);

        return back();
    }

    public function destroy(Product $product): RedirectResponse
    {
        $this->cart->remove($product);

        return back()->with('success', 'Item removed from cart.');
    }

    public function clear(): RedirectResponse
    {
        $this->cart->clear();

        return back()->with('success', 'Cart cleared.');
    }
}
