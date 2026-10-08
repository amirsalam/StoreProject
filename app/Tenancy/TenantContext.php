<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Process-scoped holder for the current tenant. Bound as a singleton
 * in the container so any code can grab the active tenant via
 *   app(TenantContext::class)->current()
 * or the tenant() helper.
 *
 * On central-domain requests (marketing/auth/admin), the current
 * tenant is null. Code that needs tenant scoping must handle that
 * explicitly — see the BelongsToTenant trait in M3.
 */
class TenantContext
{
    private ?Tenant $current = null;

    public function set(?Tenant $tenant): void
    {
        $this->current = $tenant;
    }

    public function current(): ?Tenant
    {
        return $this->current;
    }

    public function id(): ?int
    {
        return $this->current?->id;
    }

    public function hasTenant(): bool
    {
        return $this->current !== null;
    }

    public function isCentral(): bool
    {
        return $this->current === null;
    }
}
