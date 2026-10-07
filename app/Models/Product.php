<?php

namespace App\Models;

use App\Services\LicensingSettings;
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
        'extended_price',
        'currency',
        'thumbnail',
        'live_preview_url',
        'gallery',
        'download_file_path',
        'download_file_disk',
        'download_file_name',
        'download_file_size',
        'extended_file_path',
        'extended_file_disk',
        'extended_file_name',
        'extended_file_size',
        'version',
        'license_type',
        'default_activation_limit',
        'download_limit',
        'support_months',
        'support_extension_price',
        'status',
        'is_featured',
        'seo_title',
        'seo_description',
        'sales_count',
    ];

    /**
     * Where the deliverable sits on the private disk — never sent to a
     * browser. Pages get download_file_name / download_file_size instead.
     *
     * @var list<string>
     */
    protected $hidden = [
        'download_file_path',
        'download_file_disk',
        'extended_file_path',
        'extended_file_disk',
    ];

    protected function casts(): array
    {
        return [
            'download_file_size' => 'integer',
            'extended_file_size' => 'integer',
            'price' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'extended_price' => 'decimal:2',
            'support_extension_price' => 'decimal:2',
            'support_months' => 'integer',
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

    /**
     * Whether a file has been uploaded for buyers to download.
     */
    public function hasDownloadFile(): bool
    {
        return filled($this->download_file_path);
    }

    /**
     * Whether the Extended License has its own file (otherwise Extended
     * buyers get the product file).
     */
    public function hasExtendedFile(): bool
    {
        return filled($this->extended_file_path);
    }

    /** Whether buyers can choose an Extended License. */
    public function offersExtendedLicense(): bool
    {
        return $this->effectiveExtendedPrice() !== null;
    }

    /**
     * What an Extended License costs: the product's own Extended price, or
     * the store-wide default (Admin → Licensing: regular price × N).
     * Subscriptions never have one.
     */
    public function effectiveExtendedPrice(): ?string
    {
        if ($this->type === self::TYPE_SUBSCRIPTION) {
            return null;
        }

        if ($this->extended_price !== null) {
            return (string) $this->extended_price;
        }

        $default = app(LicensingSettings::class)->defaultExtendedPrice($this->price);

        return $default !== null ? number_format($default, 2, '.', '') : null;
    }

    /**
     * Serialized for the product page (`$product->append(...)`).
     */
    public function getEffectiveExtendedPriceAttribute(): ?string
    {
        return $this->effectiveExtendedPrice();
    }

    /** Whether buyers can extend the included support to 12 months. */
    public function offersSupportExtension(): bool
    {
        return $this->support_extension_price !== null && $this->support_months > 0 && $this->support_months < 12;
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
