<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Ship the four default SaaS pricing tiers.
 *
 * Limit shape: integer cap, or null for "unlimited".
 * Feature shape: list of string flags consumed by PlanGate::allows().
 *
 * Idempotent — uses updateOrCreate keyed on slug.
 */
class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'slug' => Plan::SLUG_FREE,
                'name' => 'Starter',
                'description' => 'Get your storefront live this weekend.',
                'monthly_cents' => 0,
                'annual_cents' => 0,
                'limits' => [
                    'users' => 3,
                    'products' => 10,
                    'projects' => 3,
                    'storage_gb' => 1,
                ],
                'features' => [],
                'is_default' => true,
                'sort_order' => 10,
            ],
            [
                'slug' => Plan::SLUG_PRO,
                'name' => 'Pro',
                'description' => 'For makers shipping to their first 1,000 customers.',
                'monthly_cents' => 2900,
                'annual_cents' => 29000,
                'limits' => [
                    'users' => 10,
                    'products' => 100,
                    'projects' => 25,
                    'storage_gb' => 50,
                ],
                'features' => [
                    'api_access',
                    'custom_branding',
                    'analytics_export',
                    'priority_support',
                ],
                'is_default' => false,
                'sort_order' => 20,
            ],
            [
                'slug' => Plan::SLUG_BUSINESS,
                'name' => 'Business',
                'description' => 'For teams scaling past their first cohort.',
                'monthly_cents' => 9900,
                'annual_cents' => 99000,
                'limits' => [
                    'users' => 50,
                    'products' => null,
                    'projects' => null,
                    'storage_gb' => 250,
                ],
                'features' => [
                    'api_access',
                    'custom_branding',
                    'custom_domains',
                    'analytics_export',
                    'sso',
                    'priority_support',
                ],
                'is_default' => false,
                'sort_order' => 30,
            ],
            [
                'slug' => Plan::SLUG_ENTERPRISE,
                'name' => 'Enterprise',
                'description' => 'Dedicated infrastructure, SLA, and audit exports.',
                'monthly_cents' => 0,                     // custom-quoted; UI shows "Talk to sales"
                'annual_cents' => 0,
                'limits' => [
                    'users' => null,
                    'products' => null,
                    'projects' => null,
                    'storage_gb' => null,
                ],
                'features' => [
                    'api_access',
                    'custom_branding',
                    'custom_domains',
                    'analytics_export',
                    'sso',
                    'audit_export',
                    'dedicated_schema',
                    'sla',
                    'priority_support',
                ],
                'is_default' => false,
                'sort_order' => 40,
            ],
        ];

        foreach ($plans as $attrs) {
            Plan::query()->updateOrCreate(
                ['slug' => $attrs['slug']],
                $attrs + ['is_active' => true, 'currency' => 'USD'],
            );
        }
    }
}
