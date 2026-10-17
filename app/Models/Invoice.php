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
 * Two kinds share this table:
 *   - written by hand for client work (no order_id), and
 *   - issued automatically for every paid store order (order_id set; see
 *     OrderInvoiceService) — already paid, and the buyer can open it from
 *     "My purchases".
 *
 * Distinct from the marketplace `payments` table (gateway charges on
 * orders) and `tenant_subscriptions` (the platform's own SaaS bill to
 * this tenant). Amounts are in the currency's minor unit (cents for USD,
 * whole yen for JPY).
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
        'order_id',
        'number',
        'status',
        'subtotal_cents',
        'discount_cents',
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
            'discount_cents' => 'integer',
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

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Next number in the tenant's sequence: INV-YYYY-0001, 0002, …
     *
     * Looks at every invoice of that tenant this year — deleted ones too,
     * since their numbers stay taken by the (tenant_id, number) unique
     * index. Scoped explicitly, so it works without a tenant in context
     * (payment webhooks).
     */
    public static function nextNumber(int $tenantId): string
    {
        $prefix = 'INV-'.now()->year.'-';

        $last = static::query()
            ->withTrashed()
            ->forTenant($tenantId)
            ->where('number', 'like', $prefix.'%')
            ->pluck('number')
            ->map(fn (string $number) => (int) substr($number, strlen($prefix)))
            ->max() ?? 0;

        return $prefix.str_pad((string) ($last + 1), 4, '0', STR_PAD_LEFT);
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, [self::STATUS_SENT, self::STATUS_OVERDUE], true)
            && $this->due_on?->isPast();
    }
}
