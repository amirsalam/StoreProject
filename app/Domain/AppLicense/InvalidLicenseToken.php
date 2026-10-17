<?php

namespace App\Domain\AppLicense;

use RuntimeException;

/**
 * A token that is forged, malformed, or not for this installation. `reason`
 * is a short machine code (shown to admins, logged, never trusted further).
 */
class InvalidLicenseToken extends RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct("License token rejected: {$reason}");
    }
}
