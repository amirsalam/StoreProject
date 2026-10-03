<?php

namespace App\Domain\Billing;

/**
 * Thrown by StripeWebhookProcessor when a duplicate event id is
 * inserted. The webhook controller catches it and returns 200 so the
 * gateway stops retrying.
 */
class DuplicateWebhookException extends \RuntimeException {}
