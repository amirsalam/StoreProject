<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_PERCENTAGE = 'percentage';

    public const TYPE_FIXED = 'fixed';

    public const UNUSABLE_INACTIVE = 'inactive';

    public const UNUSABLE_NOT_STARTED = 'not_started';

    public const UNUSABLE_EXPIRED = 'expired';

    public const UNUSABLE_USED_UP = 'used_up';

    protected $fillable = [
        'code',
        'description',
        'type',
        'value',
        'min_order_amount',
        'max_uses',
        'used_count',
        'max_uses_per_user',
        'starts_at',
        'expires_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'max_uses_per_user' => 'integer',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function isUsable(): bool
    {
        return $this->unusableReason() === null;
    }

    /**
     * Why the coupon can't be used right now — one of the UNUSABLE_*
     * constants — or null if it can. Lets checkout tell the buyer
     * "expired on …" instead of a generic "not valid".
     */
    public function unusableReason(): ?string
    {
        if (! $this->is_active) {
            return self::UNUSABLE_INACTIVE;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return self::UNUSABLE_NOT_STARTED;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return self::UNUSABLE_EXPIRED;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return self::UNUSABLE_USED_UP;
        }

        return null;
    }

    public function discountFor(float $subtotal): float
    {
        if ($this->type === self::TYPE_PERCENTAGE) {
            return round($subtotal * ((float) $this->value / 100), 2);
        }

        return min((float) $this->value, $subtotal);
    }
}
