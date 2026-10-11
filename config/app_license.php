<?php

/*
|--------------------------------------------------------------------------
| Application license (StoreProject itself)
|--------------------------------------------------------------------------
|
| Whether THIS installation of StoreProject is licensed to run, checked
| against IndependentLicenseServer. Completely separate from the product
| licenses StoreProject sells (App\Domain\Licensing, licenses table,
| /api/v1/licenses/*), which this module never touches.
|
*/

return [

    // License server base URL. HTTPS is required outside local/testing.
    'server_url' => env('APP_LICENSE_SERVER_URL'),

    // Product slug registered on the license server (the token audience).
    'product' => 'storeproject',

    // Reported to the server on activation / verification.
    'app_version' => env('APP_VERSION', '1.0.0'),

    /*
    | Ed25519 PUBLIC keys that may sign license tokens, by key id. Public
    | keys only — the private keys never leave the license server. Keys are
    | shipped here (never fetched over the network) so a hostile network
    | cannot inject its own. Add a new key in a release BEFORE the server
    | starts signing with it.
    |
    | Production keys: none yet. Generate with `php artisan licenses:keygen`
    | on the license server and paste the PUBLIC line here.
    */
    'public_keys' => [
        // 'prod-2026-11' => '…',
    ],

    // Trusted only when APP_ENV is local or testing (the dev license server).
    'development_public_keys' => [
        'dev-2026-10' => 'td6vGrzAW6VkhxSVSyPn+rGoZ72N1zAeV+ii2YTRK/0=',
    ],

    'http' => [
        'connect_timeout' => 5,
        'timeout' => 10,
        // Extra attempts on connection failures only (never on an answer).
        'retries' => 1,
        'retry_delay_ms' => 300,
    ],

];
