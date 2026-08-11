<?php

namespace App\Services\Billing;

use App\Models\BillingPlan;
use App\Models\ManualEntitlementGrant;
use App\Models\User;
use App\Models\UserEntitlement;

class EntitlementService
{
    public function __construct(private readonly CreditLedgerService $credits) {}

    public function activeSubscription(User $user, bool $lock = false): ?UserEntitlement
    {
        $query = UserEntitlement::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['active', 'gracePeriod', 'canceledActiveUntilExpiry', 'billingRetry'])
            ->where(function ($q): void {
                $q->where(function ($normal): void {
                    $normal->where('status', '!=', 'gracePeriod')
                        ->where(fn ($period) => $period->whereNull('period_end')->orWhere('period_end', '>', now()));
                })->orWhere(function ($grace): void {
                    $grace->where('status', 'gracePeriod')->where('grace_period_end', '>', now());
                });
            })
            ->where(fn ($q) => $q->whereNull('period_start')->orWhere('period_start', '<=', now()))
            ->with('plan')
            ->orderByRaw("CASE WHEN source = 'store' THEN 0 ELSE 1 END")
            ->orderByDesc('period_end');
        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->get()->first(fn (UserEntitlement $entitlement) => $entitlement->plan?->type === 'subscription');
    }

    /** @return array<string, mixed> */
    public function snapshot(User $user): array
    {
        $subscription = $this->activeSubscription($user);
        $manual = $this->activeManualGrant($user);
        $plan = $subscription->plan ?? $manual->plan ?? BillingPlan::query()->where('code', 'FREE')->first();
        $features = $plan?->features()->get()->mapWithKeys(fn ($feature) => [
            $feature->feature_key => $feature->limit_value ?? (bool) $feature->enabled,
        ])->all() ?? [];

        return [
            'billingEnabled' => (bool) config('billing.enabled'),
            'access' => [
                'hasSubscription' => $subscription !== null || $manual !== null,
                'source' => $subscription->source ?? ($manual ? 'manual' : 'free'),
                'status' => $subscription->status ?? ($manual ? 'active' : 'free'),
                'planCode' => $plan->code ?? 'FREE',
                'periodEnd' => ($subscription->period_end ?? $manual->ends_at)?->toIso8601String(),
                'autoRenewEnabled' => (bool) ($subscription->auto_renew_enabled ?? false),
                'gracePeriodEnd' => $subscription?->grace_period_end?->toIso8601String(),
            ],
            'limits' => [
                'maxVehicles' => $plan?->max_vehicles,
                'reportsPerPeriod' => $plan?->reports_per_period,
                'reportCredits' => $this->credits->balance($user),
            ],
            'features' => $features,
        ];
    }

    private function activeManualGrant(User $user): ?ManualEntitlementGrant
    {
        return ManualEntitlementGrant::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now())
            ->with('plan')
            ->latest('ends_at')
            ->first();
    }
}
