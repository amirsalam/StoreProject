<?php

namespace App\Domain\Payments\Cmi;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGateway;
use App\Tenancy\TenantContext;

/**
 * CMI (Centre Monétique Interbancaire, Morocco) — 3D_PAY_HOSTING.
 *
 * The buyer's browser POSTs a signed form to CMI's hosted payment page;
 * CMI then calls our callbackUrl server-to-server and sends the browser
 * back to okUrl / failUrl, each with the result and a HASH. Every message
 * is signed with the store's Store Key (hash "ver3": the parameters sorted
 * by name, values escaped and joined with "|", the Store Key appended,
 * SHA-512, base64), so nothing the browser or network sends is trusted
 * without it.
 *
 * Configured per store in Admin → Payment Gateways (provider "cmi"):
 * Client ID, Store Key, and the USD → MAD rate — CMI charges in dirhams
 * (ISO 4217 504) while the store prices in USD.
 */
class CmiGateway
{
    /** CMI platforms, chosen by the gateway's environment. */
    public const ENDPOINTS = [
        PaymentGateway::ENV_SANDBOX => 'https://testpayment.cmi.co.ma/fim/est3Dgate',
        PaymentGateway::ENV_PRODUCTION => 'https://payment.cmi.co.ma/fim/est3Dgate',
    ];

    public const CURRENCY_MAD = '504';

    /** CMI's code for an approved transaction. */
    public const APPROVED = '00';

    public function __construct(
        private readonly TenantContext $tenants,
    ) {}

    /**
     * The store's active CMI gateway, if it has a Client ID, a Store Key and
     * a positive exchange rate.
     */
    public function gateway(): ?PaymentGateway
    {
        if (! $this->tenants->hasTenant()) {
            return null;
        }

        $gateway = PaymentGateway::query()
            ->where('provider', 'cmi')
            ->where('is_active', true)
            ->first();

        $ok = $gateway
            && filled($gateway->credentials['client_id'] ?? null)
            && filled($gateway->credentials['store_key'] ?? null)
            && $this->rate($gateway) > 0;

        return $ok ? $gateway : null;
    }

    public function rate(PaymentGateway $gateway): float
    {
        return (float) str_replace(',', '.', (string) ($gateway->credentials['mad_rate'] ?? '0'));
    }

    /** USD → MAD, formatted the way CMI expects ("123.45"). */
    public function madAmount(float|string $usd, PaymentGateway $gateway): string
    {
        return number_format(round((float) $usd * $this->rate($gateway), 2), 2, '.', '');
    }

    /**
     * The signed form the browser auto-submits to CMI.
     *
     * @return array{action: string, fields: array<string, string>}
     */
    public function paymentForm(Order $order, Payment $payment, PaymentGateway $gateway, string $locale): array
    {
        $fields = [
            'clientid' => (string) $gateway->credentials['client_id'],
            'amount' => (string) ($payment->raw_response['cmi']['amount_mad'] ?? $this->madAmount($order->total, $gateway)),
            'currency' => self::CURRENCY_MAD,
            'oid' => $order->order_number,
            'okUrl' => route('checkout.cmi.ok', $order->order_number),
            'failUrl' => route('checkout.cmi.fail', $order->order_number),
            'callbackUrl' => route('payments.cmi.callback'),
            'shopurl' => route('checkout.show'),
            'TranType' => 'PreAuth',
            'storetype' => '3D_PAY_HOSTING',
            'hashAlgorithm' => 'ver3',
            'lang' => in_array($locale, ['fr', 'ar', 'en'], true) ? $locale : 'fr',
            'rnd' => bin2hex(random_bytes(10)),
            'encoding' => 'UTF-8',
            'BillToName' => (string) $order->billing_name,
            'email' => (string) $order->billing_email,
            'AutoRedirect' => 'true',
            'CallbackResponse' => 'true',
        ];

        $fields['HASH'] = self::hash($fields, (string) $gateway->credentials['store_key']);

        return [
            'action' => self::ENDPOINTS[$gateway->environment] ?? self::ENDPOINTS[PaymentGateway::ENV_SANDBOX],
            'fields' => $fields,
        ];
    }

    /**
     * Whether a message from CMI (callback or browser return) carries a valid
     * HASH for this store's Store Key.
     *
     * @param  array<string, mixed>  $params
     */
    public function verify(array $params, PaymentGateway $gateway): bool
    {
        $received = (string) ($params['HASH'] ?? $params['hash'] ?? '');
        if ($received === '') {
            return false;
        }

        return hash_equals(self::hash($params, (string) $gateway->credentials['store_key']), $received);
    }

    /**
     * CMI hash "ver3".
     *
     * @param  array<string, mixed>  $params
     */
    public static function hash(array $params, string $storeKey): string
    {
        $keys = array_keys($params);
        natcasesort($keys);

        $plain = '';
        foreach ($keys as $key) {
            if (in_array(strtolower((string) $key), ['hash', 'encoding'], true)) {
                continue;
            }
            $plain .= self::escape((string) $params[$key]).'|';
        }
        $plain .= self::escape($storeKey);

        return base64_encode(pack('H*', hash('sha512', $plain)));
    }

    private static function escape(string $value): string
    {
        return str_replace('|', '\\|', str_replace('\\', '\\\\', trim($value)));
    }
}
