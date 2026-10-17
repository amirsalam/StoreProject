<?php

namespace App\Domain\Marketplace;

/**
 * How a tenant admits newly opened vendor stores (marketplace doc §21).
 */
enum VendorApprovalMode: string
{
    /** New vendors start pending and need an admin to approve them. */
    case Manual = 'manual';

    /** New vendors start active (self-serve). */
    case Auto = 'auto';
}
