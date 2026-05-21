<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Session-backed shopping cart.
 *
 * The cart stores an associative array keyed by product ID:
 *   ['<product_id>' => ['quantity' => 2], ...]
 *
 * Subscriptions, API access, and license products are quantity-locked to 1
 * since selling 5x of "Pro Plan" doesn't make sense.
 */
class CartService
{
    private const SESSION_KEY = 'cart.items';

    private const QUANTITY_LOCKED_TYPES = [
        Product::TYPE_SUBSCRIPTION,
        Product::TYPE_API_ACCESS,
        Product::TYPE_LICENSE,
    ];

    public function __construct(private readonly Session $session) {}

    /**
     * Add a product to the cart, incrementing quantity if already present.
     */
    public function add(Product $product, int $quantity = 1): void
    {
        $quantity = max(1, $quantity);
        $items = $this->raw();
        $key = (string) $product->id;

        $existing = $items[$key]['quantity'] ?? 0;
        $newQty = $this->clampQuantity($product, $existing + $quantity);

        $items[$key] = ['quantity' => $newQty];
        $this->session->put(self::SESSION_KEY, $items);
    }

    /**
     * Set an exact quantity for a product (used by the cart page qty controls).
     */
    public function update(Product $product, int $quantity): void
    {
        $items = $this->raw();
        $key = (string) $product->id;

        if ($quantity <= 0) {
            unset($items[$key]);
        } else {
            $items[$key] = ['quantity' => $this->clampQuantity($product, $quantity)];
        }

        $this->session->put(self::SESSION_KEY, $items);
    }

    public function remove(Product $product): void
    {
        $items = $this->raw();
        unset($items[(string) $product->id]);
        $this->session->put(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * @return Collection<int, array{product: Product, quantity: int, unit_price: float, line_total: float}>
     */
    public function lineItems(): Collection
    {
        $items = $this->raw();
        if (empty($items)) {
            return collect();
        }

        $products = Product::query()
            ->whereIn('id', array_keys($items))
            ->where('status', Product::STATUS_PUBLISHED)
            ->get()
            ->keyBy('id');

        return collect($items)
            ->map(function (array $row, $id) use ($products) {
                $product = $products->get((int) $id);
                if (! $product) {
                    return null;
                }

                $unit = (float) ($product->sale_price ?? $product->price);
                $qty = (int) $row['quantity'];

                return [
                    'product' => $product,
                    'quantity' => $qty,
                    'unit_price' => $unit,
                    'line_total' => round($unit * $qty, 2),
                ];
            })
            ->filter()
            ->values();
    }

    public function count(): int
    {
        return (int) collect($this->raw())->sum('quantity');
    }

    public function subtotal(): float
    {
        return round($this->lineItems()->sum('line_total'), 2);
    }

    /**
     * Lightweight summary suitable for sharing via Inertia on every request.
     *
     * @return array{count: int, subtotal: float, currency: string}
     */
    public function summary(): array
    {
        return [
            'count' => $this->count(),
            'subtotal' => $this->subtotal(),
            'currency' => 'USD',
        ];
    }

    /**
     * @return array<string, array{quantity: int}>
     */
    private function raw(): array
    {
        $items = $this->session->get(self::SESSION_KEY, []);

        return is_array($items) ? $items : [];
    }

    private function clampQuantity(Product $product, int $quantity): int
    {
        if (in_array($product->type, self::QUANTITY_LOCKED_TYPES, true)) {
            return 1;
        }

        return min(max(1, $quantity), 99);
    }
}
