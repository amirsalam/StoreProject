<?php

namespace Tests\Feature\Payments;

use App\Domain\Payments\ReconciliationService;
use App\Domain\Payments\WalletService;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_repairs_orders_behind_succeeded_payments(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING,
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => Payment::STATUS_SUCCEEDED,
            'processed_at' => now()->subHours(1),
            'gateway_payment_id' => 'pi_'.uniqid(),
        ]);

        $report = app(ReconciliationService::class)->runAll();

        $this->assertSame(1, $report['orders_repaired']);
        $order->refresh();
        $this->assertSame(Order::STATUS_PAID, $order->status);
        $this->assertNotNull($order->paid_at);

        $this->assertDatabaseHas('activity_logs', [
            'event' => 'reconciler.repaired_order',
            'user_id' => $user->id,
        ]);
    }

    public function test_ledger_drift_detection(): void
    {
        $wallets = app(WalletService::class);
        $user = User::factory()->create();
        $wallet = $wallets->forUser($user, 'USD');

        // Healthy credit.
        $wallets->credit($wallet, 1000, 'good');
        $this->assertSame(0, app(ReconciliationService::class)->detectLedgerDrift());

        // Now manually corrupt the wallet balance to simulate drift.
        DB::table('wallets')->where('id', $wallet->id)->update(['balance_cents' => 999]);

        $drift = app(ReconciliationService::class)->detectLedgerDrift();

        $this->assertSame(1, $drift);
        $this->assertDatabaseHas('activity_logs', [
            'event' => 'reconciler.ledger_drift',
            'user_id' => $user->id,
        ]);
    }

    public function test_no_repair_when_states_already_aligned(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PAID,
            'paid_at' => now(),
        ]);
        Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'status' => Payment::STATUS_SUCCEEDED,
            'gateway_payment_id' => 'pi_'.uniqid(),
        ]);

        $report = app(ReconciliationService::class)->runAll();

        $this->assertSame(0, $report['orders_repaired']);
        $this->assertSame(0, $report['ledger_drift']);
    }

    public function test_poll_stale_payments_replays_succeeded_state(): void
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING,
            'total' => 49.00,
            'currency' => 'USD',
        ]);
        $payment = Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => 49.00,
            'currency' => 'USD',
            'gateway' => 'stripe',
            'gateway_payment_id' => 'pi_stale',
            'status' => Payment::STATUS_PENDING,
            'created_at' => now()->subMinutes(30),
        ]);

        // Stubbed gateway lookup: pretend Stripe says the intent succeeded.
        $polled = app(ReconciliationService::class)->pollStalePayments(
            fn (Payment $p) => ['status' => 'succeeded'],
        );

        $this->assertSame(1, $polled);

        $payment->refresh();
        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->status);
        $this->assertSame(Order::STATUS_PAID, $order->fresh()->status);
    }

    public function test_stale_payment_poll_skips_when_gateway_still_pending(): void
    {
        $user = User::factory()->create();
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'gateway' => 'stripe',
            'gateway_payment_id' => 'pi_still',
            'status' => Payment::STATUS_PENDING,
            'created_at' => now()->subMinutes(30),
        ]);

        $polled = app(ReconciliationService::class)->pollStalePayments(
            fn () => ['status' => 'processing'],
        );

        $this->assertSame(0, $polled);
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }
}
