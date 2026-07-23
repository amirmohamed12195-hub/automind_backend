<?php

namespace App\Services\Ai;

use App\DTO\AiProviderResult;
use App\Exceptions\AiProviderException;
use App\Models\AiRun;
use App\Models\DiagnosticSession;
use Throwable;

class AiRunRecorder
{
    public function __construct(private AiBudgetGuard $budget, private AiCostCalculator $costs) {}

    public function record(DiagnosticSession $session, string $task, int $attempt, callable $callback): AiProviderResult
    {
        $this->budget->assertWithinBudget((string) $session->user_id);
        $started = hrtime(true);
        $run = AiRun::query()->create([
            'diagnostic_session_id' => $session->id, 'task_type' => $task, 'provider' => 'openai',
            'endpoint' => 'pending', 'model' => 'pending', 'prompt_version' => config('automind.diagnostic_prompt_version'),
            'input_hash' => hash('sha256', $session->input_hash.$task), 'status' => 'running',
            'attempt' => max(1, $attempt), 'started_at' => now(),
        ]);

        try {
            $result = $callback();
            $run->update([
                'endpoint' => $result->endpoint, 'model' => $result->model, 'provider_response_id' => $result->responseId,
                'status' => 'completed', 'latency_milliseconds' => (int) ((hrtime(true) - $started) / 1_000_000),
                'input_token_count' => $result->usage['inputTokens'] ?? $result->usage['prompt_tokens'] ?? null,
                'output_token_count' => $result->usage['outputTokens'] ?? $result->usage['completion_tokens'] ?? null,
                'cached_token_count' => $result->usage['cachedTokens'] ?? $result->usage['prompt_tokens_details']['cached_tokens'] ?? null,
                'reasoning_token_count' => $result->usage['reasoningTokens'] ?? $result->usage['completion_tokens_details']['reasoning_tokens'] ?? null,
                'estimated_provider_cost' => $this->costs->estimate($result->model, $result->usage, $result->metadata),
                'cost_currency' => 'USD', 'raw_usage_json' => $result->usage,
                'response_metadata_json' => [...$result->metadata, 'pricingVersion' => config('openai.pricing.version')],
                'completed_at' => now(),
            ]);

            return new AiProviderResult($result->data, $result->responseId, $result->model, $result->endpoint, $result->usage, [...$result->metadata, 'aiRunId' => (string) $run->id]);
        } catch (Throwable $e) {
            $run->update([
                'status' => 'failed', 'safe_error_category' => $e instanceof AiProviderException ? $e->category : 'internal',
                'safe_error_message' => $e instanceof AiProviderException ? $e->getMessage() : 'Provider processing failed.',
                'latency_milliseconds' => (int) ((hrtime(true) - $started) / 1_000_000), 'completed_at' => now(),
            ]);
            throw $e;
        }
    }
}
