<?php

namespace Tests\Feature\Payments;

use App\Domain\Billing\DuplicateWebhookException;
use App\Domain\Payments\OrderPaymentProcessor;
use App\Domain\Payments\WalletService;
use App\Events\PaymentCompleted;
use App\Models\LedgerTransaction;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderPaymentProcessorTest extends TestCase
{
    use RefreshDatabase;

    private function makePayment(): Payment
    {
        $user = User::factory()->create();
        $order = Order::factory()->create([
            'user_id' => $user->id,
            'status' => Order::STATUS_PENDING,
            'total' => 49.00,
            'currency' => 'USD',
        ]);

        return Payment::factory()->create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'amount' => 49.00,
            'currency' => 'USD',
            'gateway' => 'stripe',
            'gateway_payment_id' => 'pi_test_'.uniqid(),
            'status' => Payment::STATUS_PENDING,
        ]);
    }

    private function eventPayload(Payment $payment, string $type = 'payment_intent.succeeded'): array
    {
        return [
            'data' => ['object' => ['id' => $payment->gateway_payment_id]],
            'type' => $type,
        ];
    }

    public function test_payment_intent_succeeded_transitions_payment_and_order(): void
    {
        Event::fake([PaymentCompleted::class]);

        $payment = $this->makePayment();
        $processor = app(OrderPaymentProcessor::class);

        $processor->handle(
            'stripe',
            'evt_'.uniqid(),
            'payment_intent.succeeded',
            $this->eventPayload($payment),
        );

        $payment->refresh();
        $this->assertSame(Payment::STATUS_SUCCEEDED, $payment->status);
        $this->assertNotNull($payment->processed_at);

        $payment->order->refresh();
        $this->assertSame(Order::STATUS_PAID, $payment->order->status);
        $this->assertNotNull($payment->order->paid_at);

        Event::assertDispatched(PaymentCompleted::class);
    }

    public function test_wallet_is_credited_with_payment_amount(): void
    {
        $payment = $this->makePayment();
        $processor = app(OrderPaymentProcessor::class);

        $processor->handle('stripe', 'evt_a', 'payment_intent.succeeded', $this->eventPayload($payment));

        $wallet = app(WalletService::class)->forUser($payment->user, 'USD');
        $this->assertSame(4900, $wallet->balance_cents);

        $entry = LedgerTransaction::query()
            ->where('payment_id', $payment->id)
            ->first();
        $this->assertNotNull($entry);
        $this->assertSame(LedgerTransaction::TYPE_CREDIT, $entry->type);
        $this->assertSame(4900, $entry->amount_cents);
    }

    public function test_duplicate_webhook_event_id_is_rejected_without_side_effects(): void
    {
        $payment = $this->makePayment();
        $processor = app(OrderPaymentProcessor::class);
        $eventId = 'evt_'.uniqid();
        $payload = $this->eventPayload($payment);

        $processor->handle('stripe', $eventId, 'payment_intent.succeeded', $payload);

        $wallet = app(WalletService::class)->forUser($payment->user, 'USD');
        $this->assertSame(4900, $wallet->fresh()->balance_cents);

        // Second delivery — should throw DuplicateWebhookException
        // without applying anything.
        $this->expectException(DuplicateWebhookException::class);
        try {
            $processor->handle('stripe', $eventId, 'payment_intent.succeeded', $payload);
        } finally {
            $this->assertSame(4900, $wallet->fresh()->balance_cents);
            $this->assertSame(1, LedgerTransaction::query()->where('payment_id', $payment->id)->count());
        }
    }

    public function test_idempotent_credit_under_replay_with_different_event_ids(): void
    {
        // Even when two *different* event ids fire for the same payment
        // (e.g. payment_intent.succeeded + charge.succeeded), the wallet
        // should be credited at most once — the ledger idempotency_key
        // is keyed off the payment id.
        $payment = $this->makePayment();
        $processor = app(OrderPaymentProcessor::class);

        $processor->handle('stripe', 'evt_pi', 'payment_intent.succeeded', $this->eventPayload($payment));
        $processor->handle('stripe', 'evt_ch', 'charge.succeeded', $this->eventPayload($payment));

        $this->assertSame(1, LedgerTransaction::query()->where('payment_id', $payment->id)->count());

        $wallet = app(WalletService::class)->forUser($payment->user, 'USD');
        $this->assertSame(4900, $wallet->balance_cents);
    }

    public function test_unknown_event_type_is_recorded_but_no_op(): void
    {
        $payment = $this->makePayment();
        $processor = app(OrderPaymentProcessor::class);

        $processor->handle('stripe', 'evt_x', 'customer.created', ['data' => []]);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);

        $event = WebhookEvent::query()->where('gateway_event_id', 'evt_x')->first();
        $this->assertNotNull($event);
        $this->assertNotNull($event->processed_at);
    }

    public function test_processor_aborts_when_payment_row_is_missing(): void
    {
        $processor = app(OrderPaymentProcessor::class);

        $this->expectException(\RuntimeException::class);
        $processor->handle(
            'stripe',
            'evt_orphan',
            'payment_intent.succeeded',
            ['data' => ['object' => ['id' => 'pi_does_not_exist']]],
        );
    }

    public function test_charge_refunded_moves_payment_to_refunded_and_debits_wallet(): void
    {
        $payment = $this->makePayment();
        $processor = app(OrderPaymentProcessor::class);

        // Succeed first.
        $processor->handle('stripe', 'evt_succ', 'payment_intent.succeeded', $this->eventPayload($payment));

        // Then refund.
        $processor->handle(
            'stripe',
            'evt_ref',
            'charge.refunded',
            [
                'data' => ['object' => [
                    'payment_intent' => $payment->gateway_payment_id,
                    'amount_refunded' => 4900,
                ]],
            ],
        );

        $payment->refresh();
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->status);
        $this->assertSame(Order::STATUS_REFUNDED, $payment->order->fresh()->status);

        $wallet = app(WalletService::class)->forUser($payment->user, 'USD');
        $this->assertSame(0, $wallet->balance_cents);
    }
}
