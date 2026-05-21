<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Download extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'order_item_id',
        'downloads_count',
        'max_downloads',
        'last_ip',
        'last_downloaded_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'downloads_count' => 'integer',
            'max_downloads' => 'integer',
            'last_downloaded_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
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

    public function canDownload(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_downloads !== null && $this->downloads_count >= $this->max_downloads) {
            return false;
        }

        return true;
    }
}
