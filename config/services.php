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

    'github' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
        'redirect' => env('GITHUB_REDIRECT_URI'),
    ],

    'sub2api' => [
        'base_url' => env('SUB2API_BASE_URL'),
        'admin_email' => env('SUB2API_ADMIN_EMAIL'),
        'admin_password' => env('SUB2API_ADMIN_PASSWORD'),
        'default_group_id' => (int) env('SUB2API_DEFAULT_GROUP_ID', 0),
        'key_prefix' => env('SUB2API_KEY_PREFIX', 'sk-yap-'),
        'min_balance_to_create_key' => (float) env('SUB2API_MIN_BALANCE_TO_CREATE_KEY', 5),
        'min_balance_to_keep_active' => (float) env('SUB2API_MIN_BALANCE_TO_KEEP_ACTIVE', 2),
    ],
];
