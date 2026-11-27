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
     * A service account that is a member of the store's Shared Drive. The key file
     * path may be relative to the project root and must stay outside public/.
     */
    'google_drive' => [
        'credentials' => env('GOOGLE_SA_JSON', 'storage/app/private/google-sa.json'),
        'shared_drive_id' => env('GOOGLE_SHARED_DRIVE_ID'),
    ],

    /*
     * Admin alerts (download quota, link sharing). Empty values turn alerts off.
     */
    'telegram' => [
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'admin_chat_id' => env('TELEGRAM_ADMIN_CHAT_ID'),
    ],

    /*
     * The store's own PipraPay (v3) install for automatic bKash and Nagad payments.
     * Leave either value empty to offer manual deposits only.
     */
    'piprapay' => [
        'base_url' => env('PIPRAPAY_BASE_URL'),
        'api_key' => env('PIPRAPAY_API_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
