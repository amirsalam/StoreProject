<?php

namespace App\Domain\Marketplace;

use App\Models\Vendor;
use DomainException;

/**
 * Thrown when a moderation action isn't allowed from the vendor's
 * current status — e.g. approving an already-active vendor, or
 * suspending one that was never approved (marketplace doc §11).
 */
class InvalidVendorTransition extends DomainException
{
    public static function for(Vendor $vendor, string $action): self
    {
        return new self("Cannot {$action} a vendor that is {$vendor->status}.");
    }
}
