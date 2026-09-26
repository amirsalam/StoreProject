<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class License extends Model
{
    use BelongsToTenant, HasFactory;

    public const STATUS_ACTIVE = 'active';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'user_id',
        'product_id',
        'order_item_id',
        'license_key',
        'activation_limit',
        'activations_count',
        'activated_domains',
        'status',
        'expires_at',
        'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'activation_limit' => 'integer',
            'activations_count' => 'integer',
            'activated_domains' => 'array',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (License $license) {
            if (empty($license->license_key)) {
                $license->license_key = self::generateKey();
            }
        });
    }

    public static function generateKey(): string
    {
        $segments = [];
        for ($i = 0; $i < 4; $i++) {
            $segments[] = strtoupper(Str::random(4));
        }

        return implode('-', $segments);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function isRevoked(): bool
    {
        return $this->status === self::STATUS_REVOKED || $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->status === self::STATUS_EXPIRED
            || ($this->expires_at !== null && $this->expires_at->isPast());
    }

    /**
     * Domains / instance identifiers currently holding an activation slot.
     *
     * @return list<string>
     */
    public function activatedDomains(): array
    {
        return array_values($this->activated_domains ?? []);
    }

    public function hasActivation(string $domain): bool
    {
        return in_array($domain, $this->activatedDomains(), true);
    }

    /**
     * Whether a new domain could take a slot. Counts `activated_domains`
     * (the source of truth — see LicenseActivationService), not the
     * denormalised `activations_count`.
     */
    public function canActivate(): bool
    {
        if ($this->status !== self::STATUS_ACTIVE || $this->isRevoked() || $this->isExpired()) {
            return false;
        }

        return count($this->activatedDomains()) < $this->activation_limit;
    }
}
