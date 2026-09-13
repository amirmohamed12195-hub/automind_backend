<?php

$pricingModels = json_decode((string) env('OPENAI_PRICING_MODELS_JSON', '{}'), true);
$capabilities = json_decode((string) env('OPENAI_MODEL_CAPABILITIES_JSON', '{}'), true);
$legacyMaxOutputTokens = max(1, (int) env('OPENAI_MAX_OUTPUT_TOKENS', 5000));

return [
    'api_key' => env('OPENAI_API_KEY'),
    'base_url' => rtrim(env('OPENAI_BASE_URL', 'https://api.openai.com/v1'), '/'),
    'diagnosis_model' => env('OPENAI_DIAGNOSIS_MODEL', 'gpt-5.6-terra'),
    'vision_model' => env('OPENAI_VISION_MODEL', 'gpt-5.6-luna'),
    'audio_model' => env('OPENAI_AUDIO_MODEL', 'gpt-audio-1.5'),
    'transcription_model' => env('OPENAI_TRANSCRIPTION_MODEL', 'gpt-4o-mini-transcribe'),
    'price_search_model' => env('OPENAI_PRICE_SEARCH_MODEL', 'gpt-5.6-luna'),
    'diagnosis_reasoning_effort' => env('OPENAI_DIAGNOSIS_REASONING_EFFORT', 'low'),
    'vision_reasoning_effort' => env('OPENAI_VISION_REASONING_EFFORT', 'none'),
    'price_search_reasoning_effort' => env('OPENAI_PRICE_SEARCH_REASONING_EFFORT', 'low'),
    'timeout_seconds' => (int) env('OPENAI_REQUEST_TIMEOUT_SECONDS', 60),
    'connect_timeout_seconds' => (int) env('OPENAI_CONNECT_TIMEOUT_SECONDS', 10),
    'background_mode' => (bool) env('OPENAI_BACKGROUND_MODE', false),
    'webhook_secret' => env('OPENAI_WEBHOOK_SECRET'),
    'store_responses' => (bool) env('OPENAI_STORE_RESPONSES', false),
    'vision_detail' => env('OPENAI_VISION_DETAIL', 'high'),
    'text_verbosity' => env('OPENAI_TEXT_VERBOSITY', 'low'),
    // Stable, non-user-specific routing keys improve prompt-cache hit rates.
    'diagnosis_prompt_cache_key' => env('OPENAI_DIAGNOSIS_PROMPT_CACHE_KEY', 'automind-diagnostic'),
    'vision_prompt_cache_key' => env('OPENAI_VISION_PROMPT_CACHE_KEY', 'automind-vision'),
    // Stage-specific bounds prevent short extraction tasks from inheriting the
    // much larger bilingual diagnostic-report output allowance. The legacy
    // value remains as an upper bound for backwards-compatible deployments.
    'max_output_tokens' => $legacyMaxOutputTokens,
    'diagnosis_max_output_tokens' => (int) env('OPENAI_DIAGNOSIS_MAX_OUTPUT_TOKENS', min(4000, $legacyMaxOutputTokens)),
    'vision_max_output_tokens' => (int) env('OPENAI_VISION_MAX_OUTPUT_TOKENS', min(1800, $legacyMaxOutputTokens)),
    'audio_max_output_tokens' => (int) env('OPENAI_AUDIO_MAX_OUTPUT_TOKENS', min(700, $legacyMaxOutputTokens)),
    'price_search_max_output_tokens' => (int) env('OPENAI_PRICE_SEARCH_MAX_OUTPUT_TOKENS', min(4000, $legacyMaxOutputTokens)),
    'report_assistant_max_output_tokens' => (int) env('OPENAI_REPORT_ASSISTANT_MAX_OUTPUT_TOKENS', min(1800, $legacyMaxOutputTokens)),
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
