<?php

return [
    'enabled' => (bool) env('BILLING_ENABLED', false),
    'environment' => env('BILLING_ENVIRONMENT', 'sandbox'),
    'platforms' => [
        // Apple is the first live store. Google remains independently gated
        // until its Play Console credentials and notifications are complete.
        'apple' => (bool) env('APPLE_BILLING_ENABLED', true),
        'google' => (bool) env('GOOGLE_BILLING_ENABLED', false),
    ],
    'webhook_base_url' => env('BILLING_WEBHOOK_BASE_URL'),
    'terms_url' => env('BILLING_TERMS_URL', rtrim((string) env('APP_URL', ''), '/').'/terms'),
    'privacy_url' => env('BILLING_PRIVACY_URL', rtrim((string) env('APP_URL', ''), '/').'/privacy'),
    'reconciliation' => [
        'batch_size' => (int) env('BILLING_RECONCILIATION_BATCH_SIZE', 100),
        'stale_hours' => (int) env('BILLING_RECONCILIATION_STALE_HOURS', 12),
        'stale_reservation_hours' => (int) env('BILLING_STALE_RESERVATION_HOURS', 2),
    ],
    'apple' => [
        'bundle_id' => env('APPLE_BUNDLE_ID', 'com.automind.ai'),
        'app_id' => env('APPLE_APP_ID'),
        'issuer_id' => env('APPLE_ISSUER_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
        'private_key_path' => env('APPLE_PRIVATE_KEY_PATH'),
        'root_certificates_path' => env('APPLE_ROOT_CERTIFICATES_PATH'),
        'online_certificate_checks' => (bool) env('APPLE_ONLINE_CERTIFICATE_CHECKS', true),
        'openssl_binary' => env('APPLE_OPENSSL_BINARY', 'openssl'),
        'production_api_url' => 'https://api.storekit.apple.com',
        'sandbox_api_url' => 'https://api.storekit-sandbox.apple.com',
        'manage_url' => 'https://apps.apple.com/account/subscriptions',
    ],
    'google' => [
        'package_name' => env('GOOGLE_PLAY_PACKAGE_NAME', 'com.automind.ai'),
        'project_id' => env('GOOGLE_PLAY_PROJECT_ID'),
        'service_account' => env('GOOGLE_PLAY_SERVICE_ACCOUNT'),
        'service_account_path' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_PATH'),
        'pubsub_audience' => env('GOOGLE_PLAY_PUBSUB_AUDIENCE'),
        'pubsub_service_account_email' => env('GOOGLE_PLAY_PUBSUB_SERVICE_ACCOUNT_EMAIL'),
        'pubsub_topic' => env('GOOGLE_PLAY_PUBSUB_TOPIC'),
        'api_url' => 'https://androidpublisher.googleapis.com/androidpublisher/v3',
        'manage_url' => 'https://play.google.com/store/account/subscriptions?package='.env('GOOGLE_PLAY_PACKAGE_NAME', 'com.automind.ai'),
    ],
    'products' => [
        'single_report' => [
            'apple' => 'com.automind.ai.full_report.single.v1',
            'google' => 'automind_full_report_single_v1',
        ],
        'plus_monthly' => [
            'apple' => 'com.automind.ai.plus.monthly.v1',
            'google' => 'automind_plus_v1',
            'google_base_plan' => 'monthly-v1',
        ],
        'plus_yearly' => [
            'apple' => 'com.automind.ai.plus.yearly.v1',
            'google' => 'automind_plus_v1',
            'google_base_plan' => 'yearly-v1',
        ],
    ],
];
