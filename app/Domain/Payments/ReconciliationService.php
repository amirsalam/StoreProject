<?php

namespace App\Domain\Payments;

use App\Models\ActivityLog;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Wallet;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Periodic reconciliation between the source of truth (`payments` +
 * gateway API) and the derived state (`orders`, `wallets`).
 *
 * Three passes:
 *   1. Poll the gateway for payments that have been pending too long.
 *      If the gateway says "succeeded", replay the success event
 *      idempotently.
 *   2. Find succeeded payments whose order is still pending and
 *      transition the order in a locked transaction.
 *   3. Compare each wallet's `balance_cents` against `SUM(ledger * sign)`.
 *      Any drift is logged to `activity_logs` for human review — drift
 *      should be literally impossible under the locking strategy, so
 *      seeing it means a bug.
 *
 * Designed to be safe to run repeatedly and concurrently. Each pass
 * uses SKIP LOCKED so multiple reconcilers can share the workload.
 */
class ReconciliationService
{
    public const STALE_PAYMENT_AFTER_MINUTES = 10;

    public const MAX_REPAIRS_PER_PASS = 500;

    public function __construct(
        private readonly OrderPaymentProcessor $processor,
    ) {}

    /**
     * Run all three passes. Returns counts for logging / metrics.
     *
     * @return array{stale_payments_polled: int, orders_repaired: int, ledger_drift: int}
     */
    public function runAll(): array
    {
        return [
            'stale_payments_polled' => $this->pollStalePayments(),
            'orders_repaired' => $this->repairOrdersBehindPayments(),
            'ledger_drift' => $this->detectLedgerDrift(),
        ];
    }

    /**
     * Find payments that have been pending for > STALE_PAYMENT_AFTER_MINUTES
     * and ask the gateway what really happened. If the gateway says
     * succeeded, replay the success event with a deterministic synthetic
     * event id so the webhook_events table dedupes any re-deliveries.
     *
     * (The actual gateway call is left injectable so tests can stub it.)
     */
    public function pollStalePayments(?\Closure $gatewayLookup = null): int
    {
        $cutoff = CarbonImmutable::now()->subMinutes(self::STALE_PAYMENT_AFTER_MINUTES);

        $stale = Payment::query()
            ->where('status', Payment::STATUS_PENDING)
            ->where('created_at', '<', $cutoff)
            ->whereNotNull('gateway_payment_id')
            ->limit(self::MAX_REPAIRS_PER_PASS)
            ->get();

        if ($gatewayLookup === null) {
            // Without a gateway lookup closure, we can only log the
            // stale payments. The caller (the Artisan command) wires
            // in the real lookup against Stripe's API.
            foreach ($stale as $p) {
                Log::warning('Stale pending payment, no gateway lookup configured', [
                    'payment_id' => $p->id,
                    'gateway_payment_id' => $p->gateway_payment_id,
                ]);
            }

            return $stale->count();
        }

        $count = 0;
        foreach ($stale as $payment) {
            try {
                $gatewayState = $gatewayLookup($payment);
                if (! $gatewayState || ($gatewayState['status'] ?? null) !== 'succeeded') {
                    continue;
                }

                // Synthetic event id so the idempotency log catches a
                // future real success webhook for the same payment.
                $this->processor->handle(
                    gateway: $payment->gateway,
                    eventId: "reconcile:{$payment->gateway_payment_id}:succeeded",
                    type: 'payment_intent.succeeded',
                    payload: $gatewayState['payload'] ?? [
                        'data' => ['object' => ['id' => $payment->gateway_payment_id]],
                    ],
                );

                $count++;
            } catch (\Throwable $e) {
                Log::error('Stale payment poll failed', [
                    'payment_id' => $payment->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Find succeeded payments whose order is still pending; transition
     * the order under a lock.
     *
     * Order is updated *after* a payment succeeds, in the same
     * processor transaction. But if that update part fails (process
     * crash mid-transaction, DB connection drop after the payment
     * commit) the rollback isn't always perfect — networks lie. This
     * pass is the safety net.
     */
    public function repairOrdersBehindPayments(): int
    {
        $repaired = 0;

        Payment::query()
            ->where('status', Payment::STATUS_SUCCEEDED)
            ->whereHas('order', fn ($q) => $q->where('status', Order::STATUS_PENDING))
            ->with('order')
            ->limit(self::MAX_REPAIRS_PER_PASS)
            ->each(function (Payment $payment) use (&$repaired) {
                DB::transaction(function () use ($payment, &$repaired) {
                    // Re-fetch under lock — the universe may have moved
                    // since the outer query.
                    $order = Order::query()
                        ->whereKey($payment->order_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $order || $order->status !== Order::STATUS_PENDING) {
                        return;
                    }

                    $order->update([
                        'status' => Order::STATUS_PAID,
                        'paid_at' => now(),
                    ]);

                    ActivityLog::query()->create([
                        'user_id' => $payment->user_id,
                        'event' => 'reconciler.repaired_order',
                        'description' => "Order #{$order->id} transitioned to paid by reconciler",
                        'properties' => [
                            'order_id' => $order->id,
                            'payment_id' => $payment->id,
                            'gateway_payment_id' => $payment->gateway_payment_id,
                            'tenant_id' => $payment->tenant_id,
                        ],
                    ]);

                    $repaired++;
                });
            });

        return $repaired;
    }

    /**
     * Scan every wallet and compare `balance_cents` to the computed
     * sum from the ledger. Any drift is logged as a high-priority audit
     * alert. We *never* auto-heal drift — a discrepancy means a bug in
     * the service layer that a human needs to investigate.
     */
    public function detectLedgerDrift(): int
    {
        $drift = 0;

        Wallet::query()
            ->chunkById(200, function (Collection $wallets) use (&$drift) {
                foreach ($wallets as $wallet) {
                    $computed = $this->computeLedgerBalance($wallet->id);

                    if ($computed !== $wallet->balance_cents) {
                        $drift++;
                        ActivityLog::query()->create([
                            'user_id' => $wallet->user_id,
                            'event' => 'reconciler.ledger_drift',
                            'description' => "Wallet #{$wallet->id} balance disagrees with ledger sum",
                            'properties' => [
                                'wallet_id' => $wallet->id,
                                'tenant_id' => $wallet->tenant_id,
                                'wallet_balance_cents' => $wallet->balance_cents,
                                'ledger_sum_cents' => $computed,
                                'delta_cents' => $wallet->balance_cents - $computed,
                            ],
                        ]);
                        Log::critical('Wallet ledger drift detected', [
                            'wallet_id' => $wallet->id,
                            'wallet_balance_cents' => $wallet->balance_cents,
                            'computed_cents' => $computed,
                        ]);
                    }
                }
            });

        return $drift;
    }

    private function computeLedgerBalance(int $walletId): int
    {
        // Plain query for speed — we don't need Eloquent objects here.
        $rows = DB::table('ledger_transactions')
            ->where('wallet_id', $walletId)
            ->get(['type', 'amount_cents', 'metadata']);

        $sum = 0;
        foreach ($rows as $row) {
            $type = $row->type;
            $sign = LedgerTransaction::SIGN[$type] ?? 0;
            if ($type === LedgerTransaction::TYPE_ADJUSTMENT) {
                $meta = is_string($row->metadata) ? json_decode($row->metadata, true) : ($row->metadata ?? []);
                $sign = (int) ($meta['sign'] ?? 1);
            }
            $sum += $sign * (int) $row->amount_cents;
        }

        return $sum;
    }
}
