<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A logo in the homepage's "trusted by" strip (Admin → Partners).
 * Without a logo image, the name is shown as text.
 */
class Partner extends Model
{
    public const LOGO_DIRECTORY = 'partners';

    protected $fillable = [
        'name',
        'logo_path',
        'website_url',
        'sort_order',
        'is_active',
    ];

    protected $appends = ['logo_url'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** Shown on the homepage, in order. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('id');
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }
}
