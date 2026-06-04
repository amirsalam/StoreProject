<?php

namespace App\Domain\Plans;

use App\Models\Product;
use App\Models\Tenant;

/**
 * Counts of usage-bearing resources for plan-limit enforcement.
 *
 * Each resource has a dedicated counter so we don't accidentally
 * trigger a global-scope-emptied query — every count call goes
 * through `forTenant()` for explicit cross-tenant safety.
 *
 * Add new resources here as the product surface grows (projects,
 * tasks, storage bytes, …). Resources unknown here return 0 so a new
 * plan limit doesn't block on a missing counter — log + alert on
 * unknown resources in production if you want to catch typos.
 */
class UsageReader
{
    public function count(Tenant $tenant, string $resource): int
    {
        return match ($resource) {
            'users' => $this->users($tenant),
            'products' => $this->products($tenant),
            'projects' => 0,                                // placeholder until Projects ships
            'storage_gb' => 0,                                // placeholder; computed from media files
            default => 0,
        };
    }

    private function users(Tenant $tenant): int
    {
        return $tenant->users()->count();
    }

    private function products(Tenant $tenant): int
    {
        return Product::query()->forTenant($tenant)->count();
    }
}
