<?php

namespace Tests\Feature;

use App\Exceptions\AiProviderException;
use App\Models\AiRun;
use App\Models\DiagnosticSession;
use App\Models\Vehicle;
use App\Services\Ai\AiBudgetGuard;
use App\Services\Ai\AiCostCalculator;

class AiCostBudgetTest extends ApiTestCase
{
    public function test_versioned_rates_calculate_cost_and_daily_user_budget_blocks_new_calls(): void
    {
        config([
            'openai.pricing.models.gpt-test' => ['input' => '1.00', 'cachedInput' => '0.25', 'output' => '2.00', 'webSearchCall' => '0.01'],
            'openai.daily_user_budget_usd' => '0.010000',
            'openai.daily_global_budget_usd' => '10.000000',
        ]);
        $cost = app(AiCostCalculator::class)->estimate('gpt-test', ['inputTokens' => 1000, 'cachedTokens' => 500, 'outputTokens' => 2000], ['webSearchCalls' => 1]);
        $this->assertSame('0.014625', $cost);

        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id]);
        AiRun::query()->create([
            'diagnostic_session_id' => $session->id, 'task_type' => 'diagnostic_synthesis', 'provider' => 'openai',
            'endpoint' => '/v1/responses', 'model' => 'gpt-test', 'prompt_version' => 'test-v1',
            'input_hash' => hash('sha256', 'test'), 'status' => 'completed', 'attempt' => 1,
            'estimated_provider_cost' => $cost, 'cost_currency' => 'USD', 'started_at' => now(), 'completed_at' => now(),
        ]);

        $this->expectException(AiProviderException::class);
        $this->expectExceptionMessage('daily AI analysis budget');
        app(AiBudgetGuard::class)->assertWithinBudget((string) $user->id);
    }
}
