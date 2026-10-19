<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    /*
     | Paymob (Egypt) — online card and mobile-wallet payments for the storefront.
     | The POS never uses this: in-store payment is cash or the card terminal.
     |
     | The checkout uses Paymob's Intention API + Unified Checkout, which needs
     | the secret key (server calls), the public key (checkout URL), the HMAC
     | secret (callbacks) and at least one integration id. api_key and iframe_id
     | belong to Paymob's older Accept iframe flow; they are not read by the
     | Intention flow and are kept here only so every Paymob credential for the
     | account lives in one place.
     */
    'paymob' => [
        'base_url' => env('PAYMOB_BASE_URL', 'https://accept.paymob.com'),
        'api_key' => env('PAYMOB_API_KEY'),
        'public_key' => env('PAYMOB_PUBLIC_KEY'),
        'secret_key' => env('PAYMOB_SECRET_KEY'),
        'hmac_secret' => env('PAYMOB_HMAC_SECRET'),
        'integration_id_card' => env('PAYMOB_INTEGRATION_ID_CARD'),
        'integration_id_wallet' => env('PAYMOB_INTEGRATION_ID_WALLET'),
        'iframe_id' => env('PAYMOB_IFRAME_ID'),
        'currency' => 'EGP',
        // An unpaid online order holds its stock this long before it is released.
        'expires_after_minutes' => (int) env('PAYMOB_EXPIRES_AFTER_MINUTES', 60),
        'timeout' => (int) env('PAYMOB_TIMEOUT', 20),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
