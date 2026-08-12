<?php

return [
    'operator_name' => env('LEGAL_OPERATOR_NAME', 'AutoMind'),
    'support_email' => env('SUPPORT_EMAIL', 'support@automind-ai.net'),
    'privacy_email' => env('PRIVACY_EMAIL', env('SUPPORT_EMAIL', 'support@automind-ai.net')),
    'minimum_age' => (int) env('MINIMUM_USER_AGE', 16),
    'effective_date' => env('LEGAL_EFFECTIVE_DATE', '2026-08-11'),
    'app_store_url' => env('APP_STORE_URL'),
    'play_store_url' => env('PLAY_STORE_URL'),
    'app_links' => [
        'android_package' => env('ANDROID_APPLICATION_ID', 'com.automind.ai'),
        'android_sha256_fingerprints' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ANDROID_APP_LINK_SHA256_CERT_FINGERPRINTS', '')),
        ))),
        'apple_team_id' => env('APPLE_TEAM_ID'),
        'apple_bundle_id' => env('APPLE_BUNDLE_ID', 'com.automind.ai'),
    ],
];
