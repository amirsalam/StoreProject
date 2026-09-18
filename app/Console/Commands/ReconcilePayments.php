<?php

namespace App\Console\Commands;

use App\Domain\Payments\ReconciliationService;
use Illuminate\Console\Command;

/**
 * Run the payment reconciliation pass.
 *
 *   php artisan reconcile:payments
 *
 * Scheduled hourly with --without-overlapping in routes/console.php.
 * Three passes are run in order:
 *   1. Poll the gateway for stale pending payments.
 *   2. Repair orders that lagged behind their paid payments.
 *   3. Audit wallet balances against the ledger.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'reconcile:payments';

    protected $description = 'Reconcile payments, orders, and wallet ledger; repair stuck states.';

    public function handle(ReconciliationService $service): int
    {
        $started = microtime(true);

        $report = $service->runAll();
        $duration = round((microtime(true) - $started) * 1000);

        $this->info("Reconciled in {$duration}ms:");
        $this->line("  stale_payments_polled : {$report['stale_payments_polled']}");
        $this->line("  orders_repaired       : {$report['orders_repaired']}");
        $this->line("  ledger_drift          : {$report['ledger_drift']}");

        // Non-zero ledger drift is treated as failure exit code so the
        // scheduler / Sentry / CI can pick it up.
        return $report['ledger_drift'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
