<?php

namespace App\Domain\Payments\BankTransfer;

use App\Domain\Billing\DuplicateWebhookException;
use App\Domain\Payments\OrderPaymentProcessor;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Tenancy\TenantContext;

/**
 * Bank transfer at checkout: the order is placed unpaid, the buyer is shown
 * the store's bank details with the order number as payment reference, and
 * an admin marks it paid once the money arrives (Admin → Orders).
 *
 * Works in every currency — nothing is charged online. Configured in Admin →
 * Payment Gateways (provider "bank_transfer"): account name plus an account
 * number and/or IBAN, optionally SWIFT/BIC and instructions.
 *
 * Confirming goes through OrderPaymentProcessor like a card payment, so the
 * order is fulfilled (licenses, downloads), emailed and invoiced the same
 * way — once, even if confirmed twice.
 */
class BankTransferGateway
{
    public const PROVIDER = 'bank_transfer';

    public function __construct(
        private readonly TenantContext $tenants,
        private readonly OrderPaymentProcessor $processor,
    ) {}

    /**
     * The store's active bank-transfer gateway, if it has somewhere to pay
     * into (an account number or an IBAN).
     */
    public function gateway(): ?PaymentGateway
    {
        if (! $this->tenants->hasTenant()) {
            return null;
        }

        $gateway = PaymentGateway::query()
            ->where('provider', self::PROVIDER)
            ->where('is_active', true)
            ->first();

        $ok = $gateway && (filled($gateway->credentials['account_number'] ?? null) || filled($gateway->credentials['iban'] ?? null));

        return $ok ? $gateway : null;
    }

    /**
     * What the buyer needs to make the transfer.
     *
     * @return array{account_name: ?string, account_number: ?string, iban: ?string, swift: ?string, bank_name: ?string, instructions: ?string}
     */
    public function details(PaymentGateway $gateway): array
    {
        $value = fn (string $key) => filled($gateway->credentials[$key] ?? null) ? trim((string) $gateway->credentials[$key]) : null;

        return [
            'account_name' => $value('account_name'),
            'bank_name' => $value('bank_name'),
            'account_number' => $value('account_number'),
            'iban' => $value('iban'),
            'swift' => $value('swift'),
            'instructions' => $value('instructions'),
        ];
    }

    /**
     * The payment row's gateway_payment_id. (The reference the buyer writes
     * on the transfer is just the order number.)
     */
    public static function reference(Order $order): string
    {
        return 'bank:'.$order->order_number;
    }

    /**
     * The money arrived: mark the order paid (and fulfil, email, invoice).
     * Returns false if this isn't a pending bank-transfer order.
     */
    public function confirm(Order $order): bool
    {
        $payment = $order->payments()
            ->where('gateway', self::PROVIDER)
            ->where('gateway_payment_id', self::reference($order))
            ->latest('id')
            ->first();

        if (! $payment || $order->status === Order::STATUS_PAID) {
            return false;
        }

        if ($payment->status === Payment::STATUS_SUCCEEDED) {
            return false;
        }

        try {
            $this->processor->handle(self::PROVIDER, self::reference($order).':confirmed', 'payment_intent.succeeded', [
                'type' => 'payment_intent.succeeded',
                'data' => ['object' => [
                    'id' => self::reference($order),
                    'metadata' => ['tenant_id' => (string) $order->tenant_id],
                    'bank_transfer' => ['confirmed_at' => now()->toIso8601String()],
                ]],
            ]);
        } catch (DuplicateWebhookException) {
            return false; // confirmed by someone else a moment ago
        }

        return true;
    }
}
