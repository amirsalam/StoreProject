<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Stripe (SaaS billing)
    |--------------------------------------------------------------------------
    |
    | The platform's own billing. BillingService writes through these
    | credentials to manage tenant_subscriptions. Distinct from any
    | marketplace storefront payment integration.
    */
    'stripe' => [
        'key' => env('STRIPE_KEY'),                          // pk_test_… (public)
        'secret' => env('STRIPE_SECRET'),                    // sk_test_… (server)
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),    // whsec_…
        'price_ids' => [                                     // Stripe price ids, keyed plan-slug.cycle
            'pro_monthly' => env('STRIPE_PRICE_PRO_MONTHLY'),
            'pro_annual' => env('STRIPE_PRICE_PRO_ANNUAL'),
            'business_monthly' => env('STRIPE_PRICE_BUSINESS_MONTHLY'),
            'business_annual' => env('STRIPE_PRICE_BUSINESS_ANNUAL'),
        ],
        'portal_return_url' => env('STRIPE_PORTAL_RETURN_URL', '/workspace/billing'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Socialite OAuth Providers
    |--------------------------------------------------------------------------
    |
    | One stanza per supported "Continue with …" provider. Each callback
    | URL defaults to /auth/{provider}/callback on APP_URL, override via
    | env if you proxy OAuth through a different host (e.g. ngrok, a
    | preview deploy, a staging domain).
    */

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', env('APP_URL').'/auth/google/callback'),
    ],

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI', env('APP_URL').'/auth/github/callback'),
    ],

];
