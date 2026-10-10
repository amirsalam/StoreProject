<?php

namespace App\Domain\Marketplace;

/**
 * An order is charged in one currency, so a cart can't mix products
 * priced in different currencies.
 */
class CurrencyMismatchException extends \RuntimeException
{
    public function __construct(public readonly string $cartCurrency, public readonly string $productCurrency)
    {
        parent::__construct(__('Your cart has products priced in :cart. This product is priced in :product — check out your cart first, or empty it.', [
            'cart' => $cartCurrency,
            'product' => $productCurrency,
        ]));
    }
}
