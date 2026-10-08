<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Public-facing identity for a {@see Vendor} store. Tenant scoping is
 * inherited through the parent vendor (which is BelongsToTenant), so
 * this model is reached only via that relation.
 */
class VendorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'company_name',
        'bio',
        'logo_path',
        'banner_path',
        'website',
        'social_links',
        'contact_email',
        'contact_phone',
        'country',
        'founded_year',
    ];

    protected function casts(): array
    {
        return [
            'social_links' => 'array',
            'founded_year' => 'integer',
        ];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }
}
