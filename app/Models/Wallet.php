<?php

namespace App\Models;

use App\Support\Money;
use App\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Per-(tenant, user, currency) wallet.
 *
 * All balance math happens through {@see \App\Domain\Payments\WalletService}
 * which holds the row's pessimistic lock for the duration of a write.
 * Don't `$wallet->balance_cents += X` directly — you'll race.
 */
class Wallet extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'tenant_id',
        'user_id',
        'currency',
        'balance_cents',
        'version',
    ];

    protected function casts(): array
    {
        return [
            'balance_cents' => 'integer',
            'version' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(LedgerTransaction::class)->orderByDesc('id');
    }

    /**
     * Human-readable balance, e.g. "49.00". Use for display only.
     */
    public function getBalanceAttribute(): string
    {
        return Money::fromCents($this->balance_cents);
    }
}
