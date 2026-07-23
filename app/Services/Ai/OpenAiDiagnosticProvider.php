<?php

namespace App\Services\Ai;

use App\Contracts\AiDiagnosticProvider;
use App\DTO\AiProviderResult;

class OpenAiDiagnosticProvider implements AiDiagnosticProvider
{
    public function __construct(private OpenAiHttpTransport $transport, private OpenAiResponseParser $parser) {}

    public function synthesize(array $evidenceManifest, string $safetyIdentifier): AiProviderResult
    {
        $format = json_decode((string) file_get_contents(resource_path('ai/diagnostic_report.schema.json')), true, 512, JSON_THROW_ON_ERROR);
        $response = $this->transport->post('/responses', [
            'model' => config('openai.diagnosis_model'),
            'instructions' => file_get_contents(resource_path('ai/diagnostic_system_prompt.txt')),
            'input' => [['role' => 'user', 'content' => [['type' => 'input_text', 'text' => json_encode($evidenceManifest, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]]]],
            'text' => ['format' => $format],
            'max_output_tokens' => config('openai.max_output_tokens'),
            'store' => config('openai.store_responses'),
            'background' => config('openai.background_mode'),
            'safety_identifier' => $safetyIdentifier,
        ]);

        return new AiProviderResult($this->parser->structured($response), $response['id'] ?? null, $response['model'] ?? config('openai.diagnosis_model'), '/v1/responses', $this->parser->usage($response), ['status' => $response['status'] ?? null]);
    }
}
