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

    'google' => [
        'client_ids' => array_values(array_filter(explode(',', (string) env('GOOGLE_CLIENT_IDS', '')))),
        'jwks_url' => env('GOOGLE_JWKS_URL', 'https://www.googleapis.com/oauth2/v3/certs'),
    ],

    'apple' => [
        'client_ids' => array_values(array_filter(explode(',', (string) env(
            'APPLE_CLIENT_IDS',
            'com.automind.ai,com.automind.ai.service',
        )))),
        'jwks_url' => env('APPLE_JWKS_URL', 'https://appleid.apple.com/auth/keys'),
    ],

    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials_path' => env('FCM_CREDENTIALS_PATH', env('GOOGLE_APPLICATION_CREDENTIALS')),
        'credentials_base64' => env('FCM_CREDENTIALS_BASE64'),
        'android_channel_id' => env('FCM_ANDROID_CHANNEL_ID', 'automind_high_importance'),
        'timeout_seconds' => (int) env('FCM_TIMEOUT_SECONDS', 15),
        'validate_only' => (bool) env('FCM_VALIDATE_ONLY', false),
        'stale_token_days' => (int) env('FCM_STALE_TOKEN_DAYS', 90),
    ],

    'twilio' => [
        'account_sid' => env('TWILIO_ACCOUNT_SID'),
        'auth_token' => env('TWILIO_AUTH_TOKEN'),
        'api_key' => env('TWILIO_API_KEY'),
        'api_secret' => env('TWILIO_API_SECRET'),
        'whatsapp' => [
            'enabled' => (bool) env('TWILIO_WHATSAPP_ENABLED', false),
            'from' => env('TWILIO_WHATSAPP_FROM'),
            'content_sid' => env('TWILIO_WHATSAPP_CONTENT_SID'),
            'timeout_seconds' => (int) env('TWILIO_TIMEOUT_SECONDS', 10),
        ],
        'otp' => [
            'code_ttl_seconds' => (int) env('TWILIO_OTP_CODE_TTL_SECONDS', 600),
            'challenge_ttl_seconds' => (int) env('TWILIO_OTP_CHALLENGE_TTL_SECONDS', 1800),
            'resend_cooldown_seconds' => (int) env('TWILIO_OTP_RESEND_COOLDOWN_SECONDS', 30),
            'max_attempts' => (int) env('TWILIO_OTP_MAX_ATTEMPTS', 5),
        ],
    ],

    'geocoding' => [
        'endpoint' => env('GEOCODING_ENDPOINT'),
        'key' => env('GEOCODING_API_KEY'),
    ],

];
