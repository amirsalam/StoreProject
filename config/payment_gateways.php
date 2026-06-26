<?php

/**
 * Payment provider catalog — the single place to add or change which
 * gateways the platform supports.
 *
 * Each entry declares:
 *   - label            human name shown in the admin UI
 *   - logo             emoji/icon hint (the UI may override with an asset)
 *   - supports_webhook whether a webhook secret field is offered
 *   - fields           the credential inputs the admin form renders, keyed
 *                      by the key stored in PaymentGateway.credentials:
 *                        label    field label
 *                        secret   true => password input, encrypted, never
 *                                 returned to the frontend
 *                        required whether it must be filled to save
 *
 * Adding a new gateway = adding an entry here. No model, controller, or
 * frontend change is required for the CRUD/management surface to pick it
 * up (wiring the actual charge flow is the integration's separate job).
 */
return [

    'providers' => [

        'stripe' => [
            'label' => 'Stripe',
            'logo' => '💳',
            'supports_webhook' => true,
            'fields' => [
                'publishable_key' => ['label' => 'Publishable Key', 'secret' => false, 'required' => true],
                'secret_key' => ['label' => 'Secret Key', 'secret' => true, 'required' => true],
            ],
        ],

        'paypal' => [
            'label' => 'PayPal',
            'logo' => '🅿️',
            'supports_webhook' => true,
            'fields' => [
                'client_id' => ['label' => 'Client ID', 'secret' => false, 'required' => true],
                'client_secret' => ['label' => 'Client Secret', 'secret' => true, 'required' => true],
            ],
        ],

        'paytabs' => [
            'label' => 'PayTabs',
            'logo' => '💠',
            'supports_webhook' => true,
            'fields' => [
                'profile_id' => ['label' => 'Profile ID', 'secret' => false, 'required' => true],
                'server_key' => ['label' => 'Server Key', 'secret' => true, 'required' => true],
            ],
        ],

        'moyasar' => [
            'label' => 'Moyasar',
            'logo' => '🟢',
            'supports_webhook' => true,
            'fields' => [
                'publishable_api_key' => ['label' => 'Publishable API Key', 'secret' => false, 'required' => true],
                'secret_api_key' => ['label' => 'Secret API Key', 'secret' => true, 'required' => true],
            ],
        ],

        'hyperpay' => [
            'label' => 'HyperPay',
            'logo' => '⚡',
            'supports_webhook' => true,
            'fields' => [
                'entity_id' => ['label' => 'Entity ID', 'secret' => false, 'required' => true],
                'access_token' => ['label' => 'Access Token', 'secret' => true, 'required' => true],
            ],
        ],

        'checkout' => [
            'label' => 'Checkout.com',
            'logo' => '✅',
            'supports_webhook' => true,
            'fields' => [
                'public_key' => ['label' => 'Public Key', 'secret' => false, 'required' => true],
                'secret_key' => ['label' => 'Secret Key', 'secret' => true, 'required' => true],
            ],
        ],

        'razorpay' => [
            'label' => 'Razorpay',
            'logo' => '🔵',
            'supports_webhook' => true,
            'fields' => [
                'key_id' => ['label' => 'Key ID', 'secret' => false, 'required' => true],
                'key_secret' => ['label' => 'Key Secret', 'secret' => true, 'required' => true],
            ],
        ],

        'flutterwave' => [
            'label' => 'Flutterwave',
            'logo' => '🌊',
            'supports_webhook' => true,
            'fields' => [
                'public_key' => ['label' => 'Public Key', 'secret' => false, 'required' => true],
                'secret_key' => ['label' => 'Secret Key', 'secret' => true, 'required' => true],
                'encryption_key' => ['label' => 'Encryption Key', 'secret' => true, 'required' => false],
            ],
        ],

        'paystack' => [
            'label' => 'Paystack',
            'logo' => '🟦',
            'supports_webhook' => true,
            'fields' => [
                'public_key' => ['label' => 'Public Key', 'secret' => false, 'required' => true],
                'secret_key' => ['label' => 'Secret Key', 'secret' => true, 'required' => true],
            ],
        ],

        'square' => [
            'label' => 'Square',
            'logo' => '◼️',
            'supports_webhook' => true,
            'fields' => [
                'application_id' => ['label' => 'Application ID', 'secret' => false, 'required' => true],
                'access_token' => ['label' => 'Access Token', 'secret' => true, 'required' => true],
                'location_id' => ['label' => 'Location ID', 'secret' => false, 'required' => false],
            ],
        ],

        'authorizenet' => [
            'label' => 'Authorize.Net',
            'logo' => '🔷',
            'supports_webhook' => true,
            'fields' => [
                'login_id' => ['label' => 'API Login ID', 'secret' => false, 'required' => true],
                'transaction_key' => ['label' => 'Transaction Key', 'secret' => true, 'required' => true],
            ],
        ],

        'twocheckout' => [
            'label' => '2Checkout (Verifone)',
            'logo' => '2️⃣',
            'supports_webhook' => true,
            'fields' => [
                'merchant_code' => ['label' => 'Merchant Code', 'secret' => false, 'required' => true],
                'secret_key' => ['label' => 'Secret Key', 'secret' => true, 'required' => true],
            ],
        ],

        'amazonpay' => [
            'label' => 'Amazon Pay',
            'logo' => '📦',
            'supports_webhook' => true,
            'fields' => [
                'merchant_id' => ['label' => 'Merchant ID', 'secret' => false, 'required' => true],
                'public_key_id' => ['label' => 'Public Key ID', 'secret' => false, 'required' => true],
                'private_key' => ['label' => 'Private Key', 'secret' => true, 'required' => true],
            ],
        ],

        'googlepay' => [
            'label' => 'Google Pay',
            'logo' => '🇬',
            'supports_webhook' => false,
            'fields' => [
                'merchant_id' => ['label' => 'Merchant ID', 'secret' => false, 'required' => true],
                'gateway_merchant_id' => ['label' => 'Gateway Merchant ID', 'secret' => false, 'required' => false],
            ],
        ],

        'applepay' => [
            'label' => 'Apple Pay',
            'logo' => '',
            'supports_webhook' => false,
            'fields' => [
                'merchant_id' => ['label' => 'Merchant ID', 'secret' => false, 'required' => true],
                'merchant_certificate' => ['label' => 'Merchant Certificate', 'secret' => true, 'required' => false],
            ],
        ],

        'cod' => [
            'label' => 'Cash on Delivery',
            'logo' => '💵',
            'supports_webhook' => false,
            'fields' => [],
        ],

        'bank_transfer' => [
            'label' => 'Bank Transfer',
            'logo' => '🏦',
            'supports_webhook' => false,
            'fields' => [
                'account_name' => ['label' => 'Account Name', 'secret' => false, 'required' => false],
                'account_number' => ['label' => 'Account Number', 'secret' => false, 'required' => false],
                'iban' => ['label' => 'IBAN', 'secret' => false, 'required' => false],
                'swift' => ['label' => 'SWIFT / BIC', 'secret' => false, 'required' => false],
            ],
        ],

        'custom' => [
            'label' => 'Custom Gateway',
            'logo' => '🧩',
            'supports_webhook' => true,
            'fields' => [
                'api_key' => ['label' => 'API Key', 'secret' => true, 'required' => false],
                'api_secret' => ['label' => 'API Secret', 'secret' => true, 'required' => false],
                'endpoint' => ['label' => 'Endpoint URL', 'secret' => false, 'required' => false],
            ],
        ],

    ],

];
