<?php

return [
    'enabled' => (bool) env('KASHIER_ENABLED', false),
    'web_enabled' => (bool) env('KASHIER_WEB_ENABLED', false),
    'mobile_enabled' => (bool) env('KASHIER_MOBILE_ENABLED', true),
    'mode' => env('KASHIER_MODE', 'test'),
    'merchant_id' => env('KASHIER_MERCHANT_ID'),
    'api_key' => env('KASHIER_API_KEY'),
    'secret_key' => env('KASHIER_SECRET_KEY'),
    'allowed_methods' => 'card,wallet',
    'session_minutes' => 30,
    // Prices are explicit EGP amounts. Missing prices disable that plan.
    'prices' => [
        'SINGLE_FULL_REPORT' => env('KASHIER_SINGLE_REPORT_EGP'),
        'PLUS_MONTHLY' => env('KASHIER_PLUS_MONTHLY_EGP'),
        'PLUS_YEARLY' => env('KASHIER_PLUS_YEARLY_EGP'),
    ],
];
