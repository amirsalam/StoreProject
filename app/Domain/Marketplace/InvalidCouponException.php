<?php

namespace App\Domain\Marketplace;

/**
 * Thrown when a supplied coupon code is unknown, expired, exhausted, or
 * fails an eligibility rule (minimum order, per-user limit). The message
 * is safe to surface to the customer as a field-level validation error.
 */
class InvalidCouponException extends \RuntimeException {}
