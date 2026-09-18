<?php

namespace App\Events;

use App\Models\User;
use App\Models\Vendor;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitted after a moderation action moves a vendor between lifecycle
 * states (marketplace doc §11). Dispatched only once the transition's
 * transaction commits, so listeners never see a rolled-back change.
 *
 * $action is the verb that caused it: approved, rejected, suspended,
 * or reinstated.
 */
class VendorStatusChanged implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Vendor $vendor,
        public readonly string $action,
        public readonly string $fromStatus,
        public readonly ?User $actor = null,
        public readonly ?string $reason = null,
    ) {}
}
