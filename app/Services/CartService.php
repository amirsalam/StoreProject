<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Collection;

/**
 * Session-backed shopping cart.
 *
 * Each cart line is a product plus its purchase options, keyed by
 * {@see self::lineKey()}:
 *   '12'     => Regular License, included support
 *   '12-x'   => Extended License
 *   '12-s'   => Regular License + support extended to 12 months
 *   '12-x-s' => both
 * so the same product can sit in the cart under different options, and
 * a plain product id still addresses the default line.
 *
 * Every license unit gets its own key at fulfilment, so licenses can be
 * bought in quantity; subscriptions stay locked to 1.
 */
class CartService
{
    private const SESSION_KEY = 'cart.items';

    private const QUANTITY_LOCKED_TYPES = [
        Product::TYPE_SUBSCRIPTION,
    ];

    /** Route pattern for a cart line key. */
    public const LINE_PATTERN = '[0-9]+(-x)?(-s)?';

    public function __construct(private readonly Session $session) {}

    public static function lineKey(int $productId, bool $extended = false, bool $extendedSupport = false): string
    {
        return $productId.($extended ? '-x' : '').($extendedSupport ? '-s' : '');
    }

    /**
     * Price of one unit with the chosen options. Options the product
     * doesn't offer are ignored.
     */
    public static function unitPrice(Product $product, bool $extended = false, bool $extendedSupport = false): float
    {
        $base = $extended && $product->offersExtendedLicense()
            ? (float) $product->extended_price
            : (float) ($product->sale_price ?? $product->price);

        $support = $extendedSupport && $product->offersSupportExtension()
            ? (float) $product->support_extension_price
            : 0.0;

        return round($base + $support, 2);
    }

    /**
     * Add a product (with options) to the cart, incrementing the quantity
     * of an identical line.
     */
    public function add(Product $product, int $quantity = 1, bool $extended = false, bool $extendedSupport = false): void
    {
        $extended = $extended && $product->offersExtendedLicense();
        $extendedSupport = $extendedSupport && $product->offersSupportExtension();

        $items = $this->raw();
        $key = self::lineKey($product->id, $extended, $extendedSupport);
        $existing = $items[$key]['quantity'] ?? 0;

        $items[$key] = [
            'quantity' => $this->clampQuantity($product, $existing + max(1, $quantity)),
            'extended' => $extended,
            'extended_support' => $extendedSupport,
        ];
        $this->session->put(self::SESSION_KEY, $items);
    }

    /**
     * Set an exact quantity for a cart line (0 removes it).
     */
    public function update(string $line, int $quantity): void
    {
        $items = $this->raw();
        if (! isset($items[$line])) {
            return;
        }

        if ($quantity <= 0) {
            unset($items[$line]);
        } else {
            $product = Product::query()->find($this->productId($line));
            $items[$line]['quantity'] = $product ? $this->clampQuantity($product, $quantity) : $quantity;
        }

        $this->session->put(self::SESSION_KEY, $items);
    }

    public function remove(string $line): void
    {
        $items = $this->raw();
        unset($items[$line]);
        $this->session->put(self::SESSION_KEY, $items);
    }

    public function clear(): void
    {
        $this->session->forget(self::SESSION_KEY);
    }

    /**
     * @return Collection<int, array{key: string, product: Product, quantity: int, extended: bool, extended_support: bool, support_months: int, unit_price: float, line_total: float}>
     */
    public function lineItems(): Collection
    {
        $items = $this->raw();
        if (empty($items)) {
            return collect();
        }

        $products = Product::query()
            ->whereIn('id', array_unique(array_map(fn ($key) => $this->productId((string) $key), array_keys($items))))
            ->where('status', Product::STATUS_PUBLISHED)
            ->get()
            ->keyBy('id');

        return collect($items)
            ->map(function (array $row, $key) use ($products) {
                $product = $products->get($this->productId((string) $key));
                if (! $product) {
                    return null;
                }

                // Options the product stopped offering fall back to the default.
                $extended = (bool) ($row['extended'] ?? false) && $product->offersExtendedLicense();
                $extendedSupport = (bool) ($row['extended_support'] ?? false) && $product->offersSupportExtension();
                $unit = self::unitPrice($product, $extended, $extendedSupport);
                $qty = (int) $row['quantity'];

                return [
                    'key' => (string) $key,
                    'product' => $product,
                    'quantity' => $qty,
                    'extended' => $extended,
                    'extended_support' => $extendedSupport,
                    'support_months' => $extendedSupport ? 12 : (int) $product->support_months,
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
     * @return array<string, array{quantity: int, extended?: bool, extended_support?: bool}>
     */
    private function raw(): array
    {
        $items = $this->session->get(self::SESSION_KEY, []);

        return is_array($items) ? $items : [];
    }

    private function productId(string $line): int
    {
        return (int) explode('-', $line)[0];
    }

    private function clampQuantity(Product $product, int $quantity): int
    {
        if (in_array($product->type, self::QUANTITY_LOCKED_TYPES, true)) {
            return 1;
        }

        return min(max(1, $quantity), 99);
    }
}
