<?php

namespace App\Domain\Plans;

use App\Models\Plan;
use App\Models\Tenant;

/**
 * Plan limit + feature gate.
 *
 * Wrap every mutating action that could push a tenant beyond its plan:
 *
 *     abort_unless($gate->allows($tenant, 'custom_domains'), 402);
 *     abort_unless($gate->withinLimit($tenant, 'products', 1), 402, 'Upgrade to Pro.');
 *
 * Returns lenient defaults (allow) when a tenant has no active
 * subscription — this is correct for accounts in the middle of a
 * billing change. The provisioner is responsible for ensuring every
 * tenant always has a subscription row.
 */
class PlanGate
{
    public function __construct(private readonly UsageReader $usage) {}

    /**
     * Does this tenant's plan include `$feature`?
     */
    public function allows(Tenant $tenant, string $feature): bool
    {
        $plan = $this->planFor($tenant);
        if (! $plan) {
            return true;
        }

        return $plan->hasFeature($feature);
    }

    /**
     * Would adding `$delta` to `$resource` stay within the plan's limit?
     *
     * `null` in plan.limits means unlimited and always passes.
     */
    public function withinLimit(Tenant $tenant, string $resource, int $delta = 1): bool
    {
        $plan = $this->planFor($tenant);
        if (! $plan) {
            return true;
        }
        $limit = $plan->limitFor($resource);
        if ($limit === null) {
            return true;
        }
        $current = $this->usage->count($tenant, $resource);

        return ($current + $delta) <= $limit;
    }

    /**
     * Snapshot of current usage relative to plan limits — suitable for
     * sharing via Inertia so the frontend can render upgrade prompts
     * before a user hits 402.
     *
     * @return array<string, array{used: int, limit: int|null, remaining: int|null}>
     */
    public function snapshot(Tenant $tenant): array
    {
        $plan = $this->planFor($tenant);
        $limits = $plan?->limits ?? [];
        $out = [];
        foreach ($limits as $resource => $limit) {
            $used = $this->usage->count($tenant, (string) $resource);
            $out[$resource] = [
                'used' => $used,
                'limit' => $limit === null ? null : (int) $limit,
                'remaining' => $limit === null ? null : max(0, (int) $limit - $used),
            ];
        }

        return $out;
    }

    private function planFor(Tenant $tenant): ?Plan
    {
        return $tenant->plan();
    }
}
