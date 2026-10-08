<?php

namespace App\Domain\Marketplace;

/**
 * Thrown when checkout is attempted with no items in the cart.
 */
class EmptyCartException extends \RuntimeException
{
    public function __construct(string $message = 'Your cart is empty.')
    {
        parent::__construct($message);
    }
}
