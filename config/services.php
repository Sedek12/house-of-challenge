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

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'upload' => [
        'provider' => env('UPLOAD_PROVIDER', 'local'),
        'cloudinary_url' => env('CLOUDINARY_URL'),
    ],

    'cron_secret' => env('CRON_SECRET'),

    'payment' => [
        'provider' => env('PAYMENT_PROVIDER', 'mock'),
        'webhook_secret' => env('PAYMENT_WEBHOOK_SECRET'),
        'public_key' => env('PAYMENT_PUBLIC_KEY'),
        'secret_key' => env('PAYMENT_SECRET_KEY'),
        'env' => env('PAYMENT_ENV', 'sandbox'),
    ],

];
