<?php

namespace App\Services\Billing;

use App\Exceptions\BillingException;
use App\Models\BillingAccount;
use App\Models\BillingPlan;
use App\Models\DiagnosticSession;
use App\Models\EntitlementPeriodUsage;
use App\Models\ReportEntitlementReservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ReportEntitlementService
{
    public function __construct(
        private readonly BillingAccountService $accounts,
        private readonly EntitlementService $entitlements,
        private readonly CreditLedgerService $credits,
    ) {}

    public function reserve(DiagnosticSession $diagnosis): ?ReportEntitlementReservation
    {
        if (! config('billing.enabled')) {
            return null;
        }

        return DB::transaction(function () use ($diagnosis): ReportEntitlementReservation {
            $session = DiagnosticSession::query()->whereKey($diagnosis->id)->lockForUpdate()->firstOrFail();
            $user = User::query()->whereKey($session->user_id)->firstOrFail();
            $this->lockAccount($user);
            $reservation = ReportEntitlementReservation::query()->where('diagnostic_session_id', $session->id)->lockForUpdate()->first();
            if ($reservation && in_array($reservation->status, ['reserved', 'finalized'], true)) {
                return $reservation;
            }

            $subscription = $this->entitlements->activeSubscription($user, true);
            if ($subscription && ($limit = (int) ($subscription->plan->reports_per_period ?? 0)) > 0) {
                $start = $subscription->period_start ?? $subscription->purchase_date ?? now()->startOfMonth();
                $end = $subscription->period_end ?? now()->addMonth();
                $usage = EntitlementPeriodUsage::query()->firstOrCreate(
                    ['user_entitlement_id' => $subscription->id, 'period_start' => $start, 'period_end' => $end],
                    ['report_limit' => $limit, 'reports_used' => 0, 'reports_reserved' => 0],
                );
                $usage = EntitlementPeriodUsage::query()->whereKey($usage->id)->lockForUpdate()->firstOrFail();
                if (($usage->reports_used + $usage->reports_reserved) < $usage->report_limit) {
                    $usage->increment('reports_reserved');

                    return $this->saveReservation($reservation, $user, $session, 'subscription', $subscription->id, $usage->id);
                }
            }

            if ($this->credits->balance($user) > 0) {
                $this->credits->reserveReportLocked($user, $session);

                return $this->saveReservation($reservation, $user, $session, 'credit');
            }

            $freePlan = BillingPlan::query()->where('code', 'FREE')->first();
            $freeLimit = (int) ($freePlan->reports_per_period ?? 0);
            if ($freeLimit > 0) {
                $usedOrReserved = ReportEntitlementReservation::query()
                    ->where('user_id', $user->id)
                    ->where('source', 'free')
                    ->whereIn('status', ['reserved', 'finalized'])
                    ->count();
                if ($usedOrReserved < $freeLimit) {
                    return $this->saveReservation($reservation, $user, $session, 'free');
                }
            }

            throw new BillingException(
                $subscription ? 'REPORT_LIMIT_REACHED' : 'ENTITLEMENT_REQUIRED',
                $subscription ? 'Your report allowance for this subscription period has been used.' : 'A report credit or active subscription is required.',
                402,
            );
        }, 3);
    }

    public function finalize(DiagnosticSession|string $diagnosis): void
    {
        if (! config('billing.enabled')) {
            return;
        }
        $id = $diagnosis instanceof DiagnosticSession ? $diagnosis->id : $diagnosis;
        DB::transaction(function () use ($id): void {
            $session = DiagnosticSession::query()->whereKey($id)->lockForUpdate()->firstOrFail();
            $reservation = ReportEntitlementReservation::query()->where('diagnostic_session_id', $id)->lockForUpdate()->first();
            if (! $reservation || $reservation->status === 'finalized') {
                return;
            }
            if ($reservation->status !== 'reserved') {
                throw new \DomainException('A released report entitlement cannot be finalized.');
            }
            $user = User::query()->whereKey($session->user_id)->firstOrFail();
            $this->lockAccount($user);
            if ($reservation->source === 'subscription') {
                $usage = EntitlementPeriodUsage::query()->whereKey($reservation->entitlement_period_usage_id)->lockForUpdate()->firstOrFail();
                if ($usage->reports_reserved < 1) {
                    throw new \DomainException('Subscription report reservation is inconsistent.');
                }
                $usage->decrement('reports_reserved');
                $usage->increment('reports_used');
            } elseif ($reservation->source === 'credit') {
                $this->credits->completeReportLocked($user, $session);
            }
            $reservation->update(['status' => 'finalized', 'finalized_at' => now(), 'released_at' => null]);
        }, 3);
    }

    public function release(DiagnosticSession|string $diagnosis): void
    {
        if (! config('billing.enabled')) {
            return;
        }
        $id = $diagnosis instanceof DiagnosticSession ? $diagnosis->id : $diagnosis;
        DB::transaction(function () use ($id): void {
            $session = DiagnosticSession::query()->whereKey($id)->lockForUpdate()->first();
            if (! $session) {
                return;
            }
            $reservation = ReportEntitlementReservation::query()->where('diagnostic_session_id', $id)->lockForUpdate()->first();
            if (! $reservation || $reservation->status !== 'reserved') {
                return;
            }
            $user = User::query()->whereKey($session->user_id)->firstOrFail();
            $this->lockAccount($user);
            if ($reservation->source === 'subscription') {
                $usage = EntitlementPeriodUsage::query()->whereKey($reservation->entitlement_period_usage_id)->lockForUpdate()->first();
                if ($usage && $usage->reports_reserved > 0) {
                    $usage->decrement('reports_reserved');
                }
            } elseif ($reservation->source === 'credit') {
                $this->credits->releaseReportLocked($user, $session);
            }
            $reservation->update(['status' => 'released', 'released_at' => now()]);
        }, 3);
    }

    private function saveReservation(?ReportEntitlementReservation $reservation, User $user, DiagnosticSession $session, string $source, ?string $entitlementId = null, ?string $usageId = null): ReportEntitlementReservation
    {
        $values = [
            'user_id' => $user->id,
            'diagnostic_session_id' => $session->id,
            'user_entitlement_id' => $entitlementId,
            'entitlement_period_usage_id' => $usageId,
            'source' => $source,
            'status' => 'reserved',
            'reserved_at' => now(),
            'finalized_at' => null,
            'released_at' => null,
        ];
        if ($reservation) {
            $reservation->update($values);

            return $reservation->fresh();
        }

        return ReportEntitlementReservation::query()->create($values);
    }

    private function lockAccount(User $user): void
    {
        $account = $this->accounts->forUser($user);
        BillingAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
    }
}
