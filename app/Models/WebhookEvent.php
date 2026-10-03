<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only webhook deduplication log.
 *
 * Never UPDATE rows except to stamp processed_at / processing_error
 * after the handler completes. The unique gateway_event_id index is
 * what makes our webhook flow idempotent — a duplicate delivery hits
 * the constraint and we return 200 immediately.
 */
class WebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'gateway',
        'gateway_event_id',
        'event_type',
        'tenant_id',
        'payload',
        'received_at',
        'processed_at',
        'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
