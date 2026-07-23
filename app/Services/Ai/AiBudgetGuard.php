<?php

namespace App\Services\Ai;

use App\Exceptions\AiProviderException;
use App\Models\AiRun;

class AiBudgetGuard
{
    public function assertWithinBudget(string $userId): void
    {
        $today = now()->startOfDay();
        $globalSpend = (string) AiRun::query()->where('status', 'completed')->where('created_at', '>=', $today)->sum('estimated_provider_cost');
        $userSpend = (string) AiRun::query()
            ->join('diagnostic_sessions', 'diagnostic_sessions.id', '=', 'ai_runs.diagnostic_session_id')
            ->where('diagnostic_sessions.user_id', $userId)
            ->where('ai_runs.status', 'completed')
            ->where('ai_runs.created_at', '>=', $today)
            ->sum('ai_runs.estimated_provider_cost');

        if ($this->reached($globalSpend, (string) config('openai.daily_global_budget_usd'))) {
            throw new AiProviderException('The daily AI service budget has been reached.', 'global_budget', false);
        }
        if ($this->reached($userSpend, (string) config('openai.daily_user_budget_usd'))) {
            throw new AiProviderException('Your daily AI analysis budget has been reached.', 'user_budget', false);
        }
    }

    private function reached(string $spent, string $limit): bool
    {
        return is_numeric($limit) && is_numeric($spent) && bccomp($limit, '0', 6) > 0 && bccomp($spent, $limit, 6) >= 0;
    }
}
