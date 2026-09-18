<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A seller hosted within a tenant's marketplace. A tenant (the operator)
 * can host many vendors; each gets a public storefront at
 * /store/{vendor:slug}. See docs/marketplace-architecture.md §2/§11.
 */
class Vendor extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'owner_user_id',
        'name',
        'slug',
        'status',
        'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function profile(): HasOne
    {
        return $this->hasOne(VendorProfile::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /**
     * A store is publicly visible only when the vendor is active.
     */
    public function scopePublic($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
