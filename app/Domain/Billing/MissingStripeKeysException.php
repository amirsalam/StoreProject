<?php

namespace App\Domain\Billing;

/**
 * No usable Stripe gateway is configured for the current store, so a
 * payment can't be opened. Checkout turns this into "payments unavailable".
 */
class MissingStripeKeysException extends \RuntimeException {}
