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
        'key'    => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel'              => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'mailgun' => [
        'domain'   => env('MAILGUN_DOMAIN'),
        'secret'   => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme'   => 'https',
    ],

    'microsoft_sso' => [
        'enabled'       => env('MICROSOFT_SSO_ENABLED', false),
        'tenant'        => env('MICROSOFT_SSO_TENANT', ''),
        'client_id'     => env('MICROSOFT_SSO_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_SSO_CLIENT_SECRET'),

        'allowed_tenants' => env('MICROSOFT_SSO_ALLOWED_TENANTS', ''),
    ],

    'product_info_management' => [
        'base_url' => env('PRODUCT_INFO_MANAGEMENT_BASE_URL', 'http://127.0.0.1:8020'),
        'api_token' => env('PRODUCT_INFO_MANAGEMENT_API_TOKEN'),
        'timeout' => (int) env('PRODUCT_INFO_MANAGEMENT_TIMEOUT', 300),

        // Credentials accepted by this UnoPIM service from the PIM pipeline.
        'unopim_client' => [
            'id'       => env('PIM_UNOPIM_CLIENT_ID'),
            'secret'   => env('PIM_UNOPIM_CLIENT_SECRET'),
            'username' => env('PIM_UNOPIM_USERNAME'),
            'password' => env('PIM_UNOPIM_PASSWORD'),
        ],
    ],

];
