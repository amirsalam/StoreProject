<?php

namespace App\Domain\Marketplace;

/**
 * The payment provider refused or failed to open a payment (bad or revoked
 * keys, a network error, a Stripe outage). The order was rolled back; the
 * message is safe to show the buyer, and the provider's own error has been
 * reported to the log for the store owner.
 */
class PaymentUnavailableException extends \RuntimeException {}
