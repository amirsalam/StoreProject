<?php

namespace App\Models;

use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An invoice the tenant issues to one of their own clients.
 *
 * Distinct from the marketplace `payments` table (gateway charges on
 * orders) and `tenant_subscriptions` (the platform's own SaaS bill to
 * this tenant). This row is the deliverable a freelancer or agency
 * sends to a customer for completed work.
 */
class Invoice extends Model
{
    use BelongsToTenant, HasFactory, SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SENT = 'sent';

    public const STATUS_PAID = 'paid';

    public const STATUS_OVERDUE = 'overdue';

    public const STATUS_VOID = 'void';

    protected $fillable = [
        'tenant_id',
        'client_id',
        'project_id',
        'number',
        'status',
        'subtotal_cents',
        'tax_cents',
        'total_cents',
        'currency',
        'line_items',
        'notes',
        'issued_on',
        'due_on',
        'sent_at',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'due_on' => 'date',
            'sent_at' => 'datetime',
            'paid_at' => 'datetime',
            'line_items' => 'array',
            'subtotal_cents' => 'integer',
            'tax_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_OVERDUE], true)
            && $this->due_on?->isPast();
    }
}
