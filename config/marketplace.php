<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vendor approval mode
    |--------------------------------------------------------------------------
    |
    | Platform default for how newly opened vendor stores are admitted.
    | Each tenant can override it (tenants.settings.marketplace
    | .vendor_approval_mode) from the admin Vendors page.
    |
    |   manual — new vendors start PENDING and are reviewed before their
    |            store and products are listed (marketplace doc §11).
    |   auto   — new vendors start ACTIVE (self-serve).
    |
    | See docs/marketplace-architecture.md §11 / §21.
    |
    */

    'vendor_approval_mode' => env('MARKETPLACE_VENDOR_APPROVAL_MODE', 'manual'),

];
