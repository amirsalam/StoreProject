<?php

namespace App\Events;

use App\Models\Vendor;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitted when a new vendor (seller) is registered within a tenant's
 * marketplace. Listeners (welcome notification, onboarding checklist
 * tick, operator review queue) fan out from here.
 */
class VendorRegistered
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Vendor $vendor,
    ) {}
}
