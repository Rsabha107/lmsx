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

    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI'),
        'tenant' => env('MICROSOFT_TENANT_ID'),
        // Email domains of SC/LOC staff, who are granted access and sign in with Microsoft (no password).
        'staff_domains' => array_values(array_filter(array_map(fn ($d) => strtolower(trim($d)), explode(',', (string) env('SSO_STAFF_DOMAINS', 'sc.qa,loc.qa'))))),
    ],

    'aviationstack' => [
        'key' => env('AVIATIONSTACK_API_KEY'),
    ],

    'oag' => [
        'key' => env('OAG_SUBSCRIPTION_KEY'),
    ],

    // The API key itself lives in config('ai.providers.anthropic.key') via
    // laravel/ai's own auto-merged config — this block only holds the
    // AI Copilot's model/timeout knobs, kept in services.php to match this
    // app's existing convention (see aviationstack above).
    'anthropic' => [
        'model' => env('AI_MODEL'),
        'timeout' => (int) env('AI_TIMEOUT', 15),
    ],

    // Firebase Cloud Messaging (HTTP v1). Push stays off until a service-account JSON is set.
    'fcm' => [
        'credentials' => env('FCM_CREDENTIALS'),
        'channel' => env('FCM_ANDROID_CHANNEL', 'naqla_jobs'),
    ],

];
