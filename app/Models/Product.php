<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToTenant, HasFactory;

    public const TYPE_DIGITAL_DOWNLOAD = 'digital_download';

    public const TYPE_SUBSCRIPTION = 'subscription';

    public const TYPE_API_ACCESS = 'api_access';

    public const TYPE_LICENSE = 'license';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'category_id',
        'vendor_id',
        'title',
        'slug',
        'short_description',
        'description',
        'type',
        'price',
        'sale_price',
        'currency',
        'thumbnail',
        'gallery',
        'download_file_path',
        'version',
        'license_type',
        'default_activation_limit',
        'download_limit',
        'status',
        'is_featured',
        'seo_title',
        'seo_description',
        'sales_count',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'gallery' => 'array',
            'is_featured' => 'boolean',
            'default_activation_limit' => 'integer',
            'download_limit' => 'integer',
            'sales_count' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function licenses(): HasMany
    {
        return $this->hasMany(License::class);
    }

    public function downloads(): HasMany
    {
        return $this->hasMany(Download::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function getCurrentPriceAttribute(): string
    {
        return $this->sale_price ?? $this->price;
    }

    public function isOnSale(): bool
    {
        return $this->sale_price !== null && $this->sale_price < $this->price;
    }

    /**
     * Products a customer may see and buy: published, and either
     * operator-owned (no vendor) or sold by an ACTIVE vendor. A pending,
     * rejected, or suspended vendor's catalog stays unlisted
     * (marketplace doc §11: reviewed before listing; suspension hides
     * products). A soft-deleted vendor fails the whereHas too.
     */
    public function scopeListed(Builder $query): Builder
    {
        return $query
            ->where('status', self::STATUS_PUBLISHED)
            ->where(fn (Builder $q) => $q
                ->whereNull('vendor_id')
                ->orWhereHas('vendor', fn (Builder $v) => $v->where('status', Vendor::STATUS_ACTIVE)));
    }

    /**
     * Instance form of scopeListed() for a product already in hand.
     */
    public function isListed(): bool
    {
        if ($this->status !== self::STATUS_PUBLISHED) {
            return false;
        }

        return $this->vendor_id === null || $this->vendor?->isActive() === true;
    }
}
