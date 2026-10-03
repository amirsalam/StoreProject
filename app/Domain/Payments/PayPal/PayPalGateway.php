<?php

namespace App\Domain\Payments\PayPal;

use App\Models\Order;
use App\Models\PaymentGateway;
use App\Tenancy\TenantContext;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * PayPal Checkout through the Orders v2 REST API, redirect flow:
 *
 *  1. at checkout we create a PayPal order for the exact total and send the
 *     buyer to PayPal's approval page;
 *  2. PayPal sends the buyer back to our return URL;
 *  3. we capture the order server-side and only then mark ours paid.
 *
 * Nothing the browser sends is trusted: the capture result (status,
 * amount, currency, our order number) comes straight from PayPal's API.
 *
 * Configured per store in Admin → Payment Gateways (provider "paypal"):
 * Client ID + Client Secret of a PayPal REST app; the gateway's environment
 * picks the sandbox or live API.
 */
class PayPalGateway
{
    public const API = [
        PaymentGateway::ENV_SANDBOX => 'https://api-m.sandbox.paypal.com',
        PaymentGateway::ENV_PRODUCTION => 'https://api-m.paypal.com',
    ];

    /** Currencies PayPal can charge in (PayPal REST docs). */
    public const CURRENCIES = [
        'AUD', 'BRL', 'CAD', 'CNY', 'CZK', 'DKK', 'EUR', 'HKD', 'HUF', 'ILS', 'JPY', 'MYR', 'MXN',
        'TWD', 'NZD', 'NOK', 'PHP', 'PLN', 'GBP', 'SGD', 'SEK', 'CHF', 'THB', 'USD',
    ];

    /** PayPal takes these without decimals. */
    private const NO_DECIMALS = ['HUF', 'JPY', 'TWD'];

    public function __construct(private readonly TenantContext $tenants) {}

    public static function supportsCurrency(string $currency): bool
    {
        return in_array(strtoupper($currency), self::CURRENCIES, true);
    }

    /**
     * The store's active PayPal gateway, if it has its REST credentials.
     */
    public function gateway(): ?PaymentGateway
    {
        if (! $this->tenants->hasTenant()) {
            return null;
        }

        $gateway = PaymentGateway::query()
            ->where('provider', 'paypal')
            ->where('is_active', true)
            ->first();

        return $gateway
            && filled($gateway->credentials['client_id'] ?? null)
            && filled($gateway->credentials['client_secret'] ?? null)
            ? $gateway
            : null;
    }

    /**
     * Create the PayPal order for a pending order of ours.
     *
     * @return array{id: string, approve_url: string}
     *
     * @throws RequestException
     */
    public function createOrder(PaymentGateway $gateway, Order $order, string $returnUrl, string $cancelUrl, string $brand): array
    {
        $response = $this->api($gateway)
            ->withHeaders(['PayPal-Request-Id' => 'order-'.$order->order_number.'-'.now()->timestamp])
            ->post('/v2/checkout/orders', [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $order->order_number,
                    'custom_id' => $order->order_number,
                    'invoice_id' => $order->order_number.'-'.now()->timestamp,
                    'description' => mb_substr($brand.' — '.$order->order_number, 0, 127),
                    'amount' => [
                        'currency_code' => $order->currency,
                        'value' => self::amount($order->total, $order->currency),
                    ],
                ]],
                'payment_source' => ['paypal' => ['experience_context' => [
                    'brand_name' => mb_substr($brand, 0, 127),
                    'user_action' => 'PAY_NOW',
                    'shipping_preference' => 'NO_SHIPPING',
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ]]],
            ])
            ->throw()
            ->json();

        $approve = collect($response['links'] ?? [])->firstWhere('rel', 'payer-action')
            ?? collect($response['links'] ?? [])->firstWhere('rel', 'approve');

        if (empty($response['id']) || empty($approve['href'])) {
            throw new \RuntimeException('PayPal did not return an approval link.');
        }

        return ['id' => $response['id'], 'approve_url' => $approve['href']];
    }

    /**
     * Capture an approved PayPal order. Returns PayPal's order resource.
     *
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function capture(PaymentGateway $gateway, string $paypalOrderId): array
    {
        return $this->api($gateway)
            ->withHeaders(['PayPal-Request-Id' => 'capture-'.$paypalOrderId])
            ->withBody('{}', 'application/json')
            ->post('/v2/checkout/orders/'.rawurlencode($paypalOrderId).'/capture')
            ->throw()
            ->json();
    }

    /**
     * @return array<string, mixed>
     *
     * @throws RequestException
     */
    public function getOrder(PaymentGateway $gateway, string $paypalOrderId): array
    {
        return $this->api($gateway)
            ->get('/v2/checkout/orders/'.rawurlencode($paypalOrderId))
            ->throw()
            ->json();
    }

    /**
     * An OAuth access token for the gateway's REST app, cached until shortly
     * before it expires. Also what "Test connection" checks.
     *
     * @throws RequestException
     */
    public function accessToken(PaymentGateway $gateway): string
    {
        $key = 'paypal-token:'.$gateway->id.':'.md5($gateway->environment.($gateway->credentials['client_id'] ?? ''));

        if ($token = Cache::get($key)) {
            return $token;
        }

        $response = Http::asForm()
            ->withBasicAuth((string) $gateway->credentials['client_id'], (string) $gateway->credentials['client_secret'])
            ->acceptJson()
            ->timeout(20)
            ->post($this->baseUrl($gateway).'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
            ->throw()
            ->json();

        Cache::put($key, $response['access_token'], now()->addSeconds(max(60, (int) ($response['expires_in'] ?? 3600) - 120)));

        return $response['access_token'];
    }

    public function baseUrl(PaymentGateway $gateway): string
    {
        return self::API[$gateway->environment] ?? self::API[PaymentGateway::ENV_SANDBOX];
    }

    /** PayPal amounts are strings: "12.30", or "5000" for JPY / HUF / TWD. */
    public static function amount(float|string $value, string $currency = 'USD'): string
    {
        $decimals = in_array(strtoupper($currency), self::NO_DECIMALS, true) ? 0 : 2;

        return number_format(round((float) $value, $decimals), $decimals, '.', '');
    }

    private function api(PaymentGateway $gateway): PendingRequest
    {
        return Http::baseUrl($this->baseUrl($gateway))
            ->withToken($this->accessToken($gateway))
            ->acceptJson()
            ->asJson()
            ->timeout(30);
    }
}
