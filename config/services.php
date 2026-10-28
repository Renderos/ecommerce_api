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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => env('STRIPE_CURRENCY', 'usd'),
    ],

    'shop' => [
        // Porcentaje de impuesto aplicado al subtotal (ej: 0.19 = 19% IVA)
        'tax_rate' => (float) env('SHOP_TAX_RATE', 0.19),
    ],

    // Usuarios demo creados por UserSeeder (leídos vía config para funcionar con config:cache)
    'seed' => [
        'admin_email' => env('SEED_ADMIN_EMAIL', 'admin@ecommerce.test'),
        'admin_password' => env('SEED_ADMIN_PASSWORD', 'Admin123!'),
        'customer_email' => env('SEED_CUSTOMER_EMAIL', 'cliente@ecommerce.test'),
        'customer_password' => env('SEED_CUSTOMER_PASSWORD', 'Cliente123!'),
    ],

];
