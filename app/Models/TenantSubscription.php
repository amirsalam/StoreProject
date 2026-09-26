<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A tenant's current (or historical) SaaS plan subscription.
 *
 * Not the same thing as the existing Subscription model — that one
 * tracks customers buying recurring digital products from the
 * marketplace. This one tracks the tenant's relationship to the
 * platform's own pricing tiers.
 *
 * NOT BelongsToTenant: tenancy is the *subject* of the row, not a
 * filter on a multi-tenant query. The relationship goes the other way
 * (Tenant::currentSubscription()).
 */
class TenantSubscription extends Model
{
    use HasFactory;

    public const STATUS_TRIALING = 'trialing';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PAST_DUE = 'past_due';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

    public const CYCLE_MONTHLY = 'monthly';

    public const CYCLE_ANNUAL = 'annual';

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'billing_cycle',
        'stripe_customer_id',
        'stripe_subscription_id',
        'current_period_start',
        'current_period_end',
        'trial_ends_at',
        'cancel_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'current_period_start' => 'datetime',
            'current_period_end' => 'datetime',
            'trial_ends_at' => 'datetime',
            'cancel_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_TRIALING, self::STATUS_ACTIVE], true);
    }

    public function onTrial(): bool
    {
        return $this->status === self::STATUS_TRIALING
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    public function hasExpired(): bool
    {
        if ($this->status === self::STATUS_EXPIRED) {
            return true;
        }

        return $this->current_period_end !== null
            && $this->current_period_end->isPast()
            && ! $this->onTrial();
    }
}
