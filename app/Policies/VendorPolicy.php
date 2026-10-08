<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Vendor;

/**
 * A vendor is managed by its owner. Platform admins may manage any
 * vendor (moderation/verification). Auto-discovered by Laravel for
 * App\Models\Vendor.
 */
class VendorPolicy
{
    /**
     * Admins bypass the per-record checks below.
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('admin') || $user->is_admin) {
            return true;
        }

        return null;
    }

    public function update(User $user, Vendor $vendor): bool
    {
        return $vendor->owner_user_id === $user->id;
    }

    public function manage(User $user, Vendor $vendor): bool
    {
        return $vendor->owner_user_id === $user->id;
    }
}
