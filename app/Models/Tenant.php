<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A tenant — a single customer workspace inside the multi-tenant SaaS.
 *
 * Tenants are resolved per request from the subdomain (acme.example.com)
 * via the ResolveTenant middleware. Each tenant owns its products,
 * orders, licenses, etc. via the BelongsToTenant trait + global scope.
 */
class Tenant extends Model
{
    /** @use HasFactory<\Database\Factories\TenantFactory> */
    use HasFactory;

    public const ROLE_OWNER = 'owner';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_MEMBER = 'member';

    protected $fillable = [
        'name',
        'slug',
        'custom_domain',
        'owner_id',
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
}
