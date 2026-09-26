<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
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

    /**
     * Whether anything a customer bought still points at this product —
     * the rows whose foreign keys block deleting it (restrictOnDelete).
     * Checked across tenants, exactly like the constraint itself.
     */
    public function hasSalesHistory(): bool
    {
        foreach ([$this->orderItems(), $this->licenses(), $this->downloads(), $this->subscriptions()] as $relation) {
            if ($relation->withoutGlobalScope('tenant')->exists()) {
                return true;
            }
        }

        return false;
    }

    public function getCurrentPriceAttribute(): string
    {
        return $this->sale_price ?? $this->price;
    }

    public function isOnSale(): bool
    {
        return $this->sale_price !== null && $this->sale_price < $this->price;
    }
}
