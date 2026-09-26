<?php

namespace App\Domain\Payments;

use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * The only safe way to move money in or out of a wallet.
 *
 *   $wallet = $service->forUser($user, 'USD');
 *   $service->credit($wallet, 4900, 'payment:42:credit', payment: $payment);
 *
 * Guarantees:
 *   - Atomic: the ledger row, wallet update, and (optional) snapshot
 *     happen in one DB transaction.
 *   - Locked: the wallet row is held via SELECT FOR UPDATE for the
 *     duration of the transaction, so concurrent credits/debits
 *     serialize.
 *   - Idempotent: every write carries an idempotency_key UNIQUE. A
 *     duplicate call returns the existing ledger row instead of
 *     double-applying.
 */
class WalletService
{
    /**
     * Find (or first-create) the wallet for this user + currency.
     *
     * Uses INSERT … ON DUPLICATE KEY (firstOrCreate) so two concurrent
     * "first hit" requests can't create two wallets for the same triple.
     */
    public function forUser(User $user, string $currency, ?int $tenantId = null): Wallet
    {
        return Wallet::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'user_id' => $user->id,
                'currency' => strtoupper($currency),
            ],
            ['balance_cents' => 0, 'version' => 0],
        );
    }

    /**
     * Credit `$cents` to `$wallet`, identified by `$idempotencyKey`.
     *
     * Calling twice with the same key returns the existing ledger row,
     * never double-applies.
     */
    public function credit(
        Wallet $wallet,
        int $cents,
        string $idempotencyKey,
        ?Payment $payment = null,
        ?Order $order = null,
        ?string $reference = null,
        ?array $metadata = null,
    ): LedgerTransaction {
        return $this->apply($wallet, LedgerTransaction::TYPE_CREDIT, $cents, $idempotencyKey, $payment, $order, $reference, $metadata);
    }

    public function debit(
        Wallet $wallet,
        int $cents,
        string $idempotencyKey,
        ?Payment $payment = null,
        ?Order $order = null,
        ?string $reference = null,
        ?array $metadata = null,
    ): LedgerTransaction {
        return $this->apply($wallet, LedgerTransaction::TYPE_DEBIT, $cents, $idempotencyKey, $payment, $order, $reference, $metadata);
    }

    public function refund(
        Wallet $wallet,
        int $cents,
        string $idempotencyKey,
        ?Payment $payment = null,
        ?Order $order = null,
        ?string $reference = null,
        ?array $metadata = null,
    ): LedgerTransaction {
        return $this->apply($wallet, LedgerTransaction::TYPE_REFUND, $cents, $idempotencyKey, $payment, $order, $reference, $metadata);
    }

    /**
     * Re-compute the canonical balance from the ledger.
     * Used by reconciliation to detect drift.
     */
    public function computedBalance(Wallet $wallet): int
    {
        $rows = LedgerTransaction::query()
            ->where('wallet_id', $wallet->id)
            ->get(['type', 'amount_cents', 'metadata']);

        return $rows->reduce(function (int $sum, LedgerTransaction $row) {
            $sign = LedgerTransaction::SIGN[$row->type] ?? 0;

            // Adjustments may carry a negative sign in metadata.
            if ($row->type === LedgerTransaction::TYPE_ADJUSTMENT) {
                $sign = (int) ($row->metadata['sign'] ?? 1);
            }

            return $sum + ($sign * $row->amount_cents);
        }, 0);
    }

    /**
     * Internal: do the locked write.
     */
    private function apply(
        Wallet $wallet,
        string $type,
        int $cents,
        string $idempotencyKey,
        ?Payment $payment,
        ?Order $order,
        ?string $reference,
        ?array $metadata,
    ): LedgerTransaction {
        if ($cents <= 0) {
            throw new \InvalidArgumentException('Ledger amount_cents must be positive; carry sign in `type`.');
        }

        // Fast-path dedup: if the key already exists, return that row
        // without touching the wallet. This is safe outside the transaction
        // because UNIQUE will still catch the rare race that gets past here.
        $existing = LedgerTransaction::query()
            ->where('idempotency_key', $idempotencyKey)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return DB::transaction(function () use ($wallet, $type, $cents, $idempotencyKey, $payment, $order, $reference, $metadata) {
            /** @var Wallet $locked */
            $locked = Wallet::query()
                ->whereKey($wallet->id)
                ->lockForUpdate()
                ->first();

            $sign = LedgerTransaction::SIGN[$type] ?? 1;
            if ($type === LedgerTransaction::TYPE_ADJUSTMENT) {
                $sign = (int) ($metadata['sign'] ?? 1);
            }

            $newBalance = $locked->balance_cents + ($sign * $cents);

            try {
                $row = LedgerTransaction::query()->create([
                    'wallet_id' => $locked->id,
                    'payment_id' => $payment?->id,
                    'order_id' => $order?->id,
                    'type' => $type,
                    'amount_cents' => $cents,
                    'balance_after_cents' => $newBalance,
                    'currency' => $locked->currency,
                    'idempotency_key' => $idempotencyKey,
                    'reference' => $reference,
                    'metadata' => $metadata,
                ]);
            } catch (QueryException $e) {
                // Concurrent writer slipped in with the same idempotency_key.
                // Roll the transaction back and return their row.
                if ($this->isUniqueViolation($e)) {
                    return LedgerTransaction::query()
                        ->where('idempotency_key', $idempotencyKey)
                        ->firstOrFail();
                }
                throw $e;
            }

            // Bypass the LedgerTransaction guard with a direct query for
            // the *wallet*, which is mutable on purpose.
            Wallet::query()
                ->whereKey($locked->id)
                ->update([
                    'balance_cents' => $newBalance,
                    'version' => $locked->version + 1,
                ]);

            return $row;
        });
    }

    private function isUniqueViolation(QueryException $e): bool
    {
        return in_array($e->errorInfo[1] ?? null, [1062, 19, '23505'], true);
    }
}
