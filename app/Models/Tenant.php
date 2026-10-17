<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A tenant — a single customer workspace inside the multi-tenant SaaS.
 *
 * Tenants are resolved per request from the subdomain (acme.example.com)
 * via the ResolveTenant middleware. Each tenant owns its products,
 * orders, licenses, etc. via the BelongsToTenant trait + global scope.
 */
class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MEMBER = 'member';

    protected $fillable = [
        'name',
        'slug',
        'custom_domain',
        'owner_id',
        'stripe_customer_id',
        'settings',
        'trial_ends_at',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
        ];
    }

    /**
     * Resolve routing by slug, never by integer id, so URLs stay stable.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Members of this tenant, including the owner.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Returns the role the given user has in this tenant, or null if
     * they are not a member.
     */
    public function roleOf(User $user): ?string
    {
        $membership = $this->users()->where('users.id', $user->id)->first();

        return $membership ? (string) $membership->pivot->role : null;
    }

    /**
     * Whether the user may configure this workspace's payment gateway (the
     * Stripe keys checkout charges with): the owner, a workspace admin, or
     * a platform admin. Plain members can see billing but not the keys.
     */
    public function canManagePayments(User $user): bool
    {
        if ($user->is_admin || $user->hasRole('admin') || $this->owner_id === $user->id) {
            return true;
        }

        return in_array($this->roleOf($user), [self::ROLE_OWNER, self::ROLE_ADMIN], true);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(TenantSubscription::class);
    }

    /**
     * The current active subscription — trialing or active.
     * Returns null if the tenant has never subscribed.
     */
    public function currentSubscription(): HasOne
    {
        return $this->hasOne(TenantSubscription::class)
            ->whereIn('status', [
                TenantSubscription::STATUS_TRIALING,
                TenantSubscription::STATUS_ACTIVE,
                TenantSubscription::STATUS_PAST_DUE,
            ])
            ->latestOfMany();
    }

    public function plan(): ?Plan
    {
        return $this->currentSubscription?->plan;
    }
}
