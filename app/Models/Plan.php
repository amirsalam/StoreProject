<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SaaS pricing tier (free / pro / business / enterprise).
 *
 * Plans are global — they're shared across every tenant, so no
 * BelongsToTenant trait here.
 */
class Plan extends Model
{
    use HasFactory;

    public const SLUG_FREE = 'free';

    public const SLUG_PRO = 'pro';

    public const SLUG_BUSINESS = 'business';

    public const SLUG_ENTERPRISE = 'enterprise';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'monthly_cents',
        'annual_cents',
        'currency',
        'limits',
        'features',
        'is_active',
        'is_default',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'limits' => 'array',
            'features' => 'array',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'monthly_cents' => 'integer',
            'annual_cents' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    /**
     * Lookup a numeric limit by resource key. `null` means unlimited.
     */
    public function limitFor(string $resource): ?int
    {
        $value = $this->limits[$resource] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, (array) $this->features, true);
    }

    /**
     * The "starter" plan automatically assigned to newly provisioned
     * tenants. Falls back to the cheapest active plan if no row is
     * flagged is_default.
     */
    public static function default(): ?self
    {
        return static::query()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->orderBy('monthly_cents')
            ->first();
    }
}
