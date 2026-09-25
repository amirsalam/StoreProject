<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Contact form notification address
    |--------------------------------------------------------------------------
    |
    | Where to email a copy of each message sent through the public contact
    | form. Leave CONTACT_NOTIFY_TO unset and nothing is emailed — messages
    | are still stored and readable in the admin inbox at /admin/contact.
    |
    */

    'notify_to' => env('CONTACT_NOTIFY_TO'),

];
