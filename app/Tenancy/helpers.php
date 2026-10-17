<?php

use App\Models\Tenant;
use App\Tenancy\TenantContext;

if (! function_exists('tenant')) {
    /**
     * Get the current tenant, or null on central-domain requests.
     */
    function tenant(): ?Tenant
    {
        return app(TenantContext::class)->current();
    }
}

if (! function_exists('tenant_id')) {
    /**
     * Get the current tenant id, or null on central-domain requests.
     */
    function tenant_id(): ?int
    {
        return app(TenantContext::class)->id();
    }
}
