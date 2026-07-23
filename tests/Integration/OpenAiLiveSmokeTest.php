<?php

namespace Tests\Integration;

use App\Services\Ai\OpenAiConfigurationValidator;
use App\Services\Ai\OpenAiHttpTransport;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

#[Group('openai-live')]
class OpenAiLiveSmokeTest extends TestCase
{
    public function test_responses_api_is_reachable_only_when_explicitly_enabled(): void
    {
        if (! filter_var(env('RUN_OPENAI_LIVE_TESTS', false), FILTER_VALIDATE_BOOL)) {
            $this->markTestSkipped('Set RUN_OPENAI_LIVE_TESTS=true explicitly to spend provider credit.');
        }

        app(OpenAiConfigurationValidator::class)->validate();
        $response = app(OpenAiHttpTransport::class)->post('/responses', [
            'model' => config('openai.diagnosis_model'),
            'input' => 'Reply with exactly OK.',
            'max_output_tokens' => 32,
            'store' => false,
            'background' => false,
            'safety_identifier' => hash('sha256', 'automind-live-smoke'),
        ]);

        $this->assertNotEmpty($response['id'] ?? null);
        $this->assertContains($response['status'] ?? null, ['completed', 'incomplete']);
    }
}
