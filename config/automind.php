<?php

return [
    'api_version' => env('API_VERSION', '1.0.0'),
    'allow_demo_seeding' => (bool) env('ALLOW_DEMO_SEEDING', false),
    'diagnostic_prompt_version' => 'diagnostic-v1',
    'diagnostic_schema_version' => 'diagnostic-report-v1',
    'disclaimer_version' => '2026-07-19',
    'disclaimer' => [
        'en' => 'This AI result is an estimate and does not replace an inspection by a qualified automotive technician.',
        'ar' => 'هذه النتيجة الصادرة عن الذكاء الاصطناعي تقديرية ولا تُغني عن الفحص لدى فني سيارات مؤهل.',
    ],
    'estimate_disclaimer' => [
        'en' => 'This is an expected market range, not a repair quote. Inspection may change the required work.',
        'ar' => 'هذا نطاق سوقي متوقع وليس عرض سعر للإصلاح، وقد يغيّر الفحص الأعمال المطلوبة.',
    ],
    'media' => [
        'disk' => env('PRIVATE_FILESYSTEM_DISK', 'local'),
        'max_image_bytes' => (int) env('DIAGNOSIS_MAX_IMAGE_BYTES', 10485760),
        'max_audio_bytes' => (int) env('DIAGNOSIS_MAX_AUDIO_BYTES', 15728640),
        'max_image_dimension' => (int) env('DIAGNOSIS_MAX_IMAGE_DIMENSION', 6000),
        'provider_image_max_dimension' => (int) env('PROVIDER_IMAGE_MAX_DIMENSION', 2048),
        'signed_url_ttl_minutes' => (int) env('SIGNED_URL_TTL_MINUTES', 10),
        'clamav_command' => env('CLAMAV_COMMAND'),
        'ffmpeg_path' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
        'ffprobe_path' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),
    ],
    'price_search_ttl_days' => (int) env('PRICE_SEARCH_TTL_DAYS', 7),
    'price_source_max_age_days' => (int) env('PRICE_SOURCE_MAX_AGE_DAYS', 30),
    'retention' => [
        'raw_media_days' => (int) env('RAW_MEDIA_RETENTION_DAYS', 30),
        'ai_metadata_days' => (int) env('AI_METADATA_RETENTION_DAYS', 90),
        'audit_days' => (int) env('AUDIT_RETENTION_DAYS', 365),
        'deleted_account_grace_days' => (int) env('DELETED_ACCOUNT_GRACE_DAYS', 30),
    ],
];
