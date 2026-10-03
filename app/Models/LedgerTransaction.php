<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only ledger row.
 *
 * Intentionally has UPDATED_AT = null and a guard against `update()`:
 * the rows are immutable. A mistake becomes a `reversal` entry, never
 * an in-place edit.
 */
class LedgerTransaction extends Model
{
    use HasFactory;

    public const UPDATED_AT = null;

    public const TYPE_CREDIT = 'credit';
    public const TYPE_DEBIT = 'debit';
    public const TYPE_REFUND = 'refund';
    public const TYPE_ADJUSTMENT = 'adjustment';
    public const TYPE_REVERSAL = 'reversal';

    /**
     * @var array<string, int>  Sign per type, used by reconciliation
     *                          when re-computing balance from history.
     */
    public const SIGN = [
        self::TYPE_CREDIT => 1,
        self::TYPE_REFUND => 1,
        self::TYPE_DEBIT => -1,
        self::TYPE_REVERSAL => -1,
        self::TYPE_ADJUSTMENT => 1,        // adjustments carry sign in metadata
    ];

    protected $fillable = [
        'wallet_id',
        'payment_id',
        'order_id',
        'type',
        'amount_cents',
        'balance_after_cents',
        'currency',
        'idempotency_key',
        'reference',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'balance_after_cents' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Lock the row against accidental mutation: the ledger is append-only.
     * Use Wallet/LedgerTransaction::create + a reversal entry instead.
     */
    public function update(array $attributes = [], array $options = []): bool
    {
        throw new \LogicException('LedgerTransaction rows are immutable. Use a reversal entry.');
    }
}
