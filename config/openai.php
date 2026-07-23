<?php

$pricingModels = json_decode((string) env('OPENAI_PRICING_MODELS_JSON', '{}'), true);
$capabilities = json_decode((string) env('OPENAI_MODEL_CAPABILITIES_JSON', '{}'), true);

return [
    'api_key' => env('OPENAI_API_KEY'),
    'base_url' => rtrim(env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
    'diagnosis_model' => env('OPENAI_DIAGNOSIS_MODEL', 'gpt-5.6-terra'),
    'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-5.6-terra'),
    'audio_model' => env('OPENAI_AUDIO_MODEL', 'gpt-audio-1.5'),
    'transcription_model' => env('OPENAI_TRANSCRIPTION_MODEL', 'gpt-4o-mini-transcribe'),
    'price_search_model' => env('OPENAI_PRICE_SEARCH_MODEL', 'gpt-5.6-luna'),
    'timeout_seconds' => (int) env('OPENAI_REQUEST_TIMEOUT_SECONDS', 120),
    'background_mode' => (bool) env('OPENAI_BACKGROUND_MODE', false),
    'webhook_secret' => env('OPENAI_WEBHOOK_SECRET'),
    'store_responses' => (bool) env('OPENAI_STORE_RESPONSES', false),
    'vision_detail' => env('OPENAI_VISION_DETAIL', 'high'),
    'max_output_tokens' => (int) env('OPENAI_MAX_OUTPUT_TOKENS', 5000),
    'daily_user_budget_usd' => env('OPENAI_DAILY_USER_BUDGET_USD', '2.00'),
    'daily_global_budget_usd' => env('OPENAI_DAILY_GLOBAL_BUDGET_USD', '100.00'),
    'capabilities' => is_array($capabilities) && $capabilities !== [] ? $capabilities : [
        'responses' => ['gpt-5.6-sol', 'gpt-5.6-terra', 'gpt-5.6-luna'],
        'vision' => ['gpt-5.6-sol', 'gpt-5.6-terra', 'gpt-5.6-luna'],
        'web_search' => ['gpt-5.6-sol', 'gpt-5.6-terra', 'gpt-5.6-luna'],
        'audio_input' => ['gpt-audio-1.5'],
        'transcription' => ['gpt-4o-mini-transcribe', 'gpt-4o-transcribe'],
    ],
    'pricing' => [
        // USD per million tokens. Update only from the official pricing page.
        'version' => env('OPENAI_PRICING_VERSION', 'unconfigured'),
        'models' => is_array($pricingModels) ? $pricingModels : [],
    ],
];
