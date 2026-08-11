<?php

namespace App\Services\Billing;

use App\Models\BillingAccount;
use App\Models\CreditLedgerEntry;
use App\Models\DiagnosticSession;
use App\Models\StorePurchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreditLedgerService
{
    public function __construct(private readonly BillingAccountService $accounts) {}

    public function balance(User|string $user): int
    {
        $userId = $user instanceof User ? $user->id : $user;

        return (int) (CreditLedgerEntry::query()->where('user_id', $userId)->latest('id')->value('balance_after') ?? 0);
    }

    public function grantPurchase(User $user, StorePurchase $purchase, int $quantity = 1): CreditLedgerEntry
    {
        return DB::transaction(function () use ($user, $purchase, $quantity): CreditLedgerEntry {
            $this->lockAccount($user);

            $entry = $this->appendLocked(
                $user,
                'PURCHASE_GRANTED',
                max(1, $quantity),
                'purchase:'.$purchase->id.':credit',
                $purchase,
                null,
                'Verified consumable purchase',
            );
            if ($entry->wasRecentlyCreated) {
                Log::info('billing_credit_granted', ['purchase_id' => $purchase->id, 'ledger_entry_id' => $entry->id]);
            }

            return $entry;
        }, 3);
    }

    public function reserveReportLocked(User $user, DiagnosticSession $diagnosis): CreditLedgerEntry
    {
        $cycle = CreditLedgerEntry::query()->where('diagnostic_session_id', $diagnosis->id)->where('entry_type', 'REPORT_RESERVED')->count() + 1;

        return $this->appendLocked(
            $user,
            'REPORT_RESERVED',
            -1,
            'diagnosis:'.$diagnosis->id.':reserve:'.$cycle,
            null,
            $diagnosis,
            'Credit reserved for report generation',
        );
    }

    public function completeReportLocked(User $user, DiagnosticSession $diagnosis): CreditLedgerEntry
    {
        return $this->appendLocked(
            $user,
            'REPORT_COMPLETED',
            0,
            'diagnosis:'.$diagnosis->id.':complete',
            null,
            $diagnosis,
            'Reserved report credit finalized',
        );
    }

    public function releaseReportLocked(User $user, DiagnosticSession $diagnosis): CreditLedgerEntry
    {
        $cycle = CreditLedgerEntry::query()->where('diagnostic_session_id', $diagnosis->id)->where('entry_type', 'REPORT_RELEASED')->count() + 1;

        return $this->appendLocked(
            $user,
            'REPORT_RELEASED',
            1,
            'diagnosis:'.$diagnosis->id.':release:'.$cycle,
            null,
            $diagnosis,
            'Report generation did not complete; credit returned',
        );
    }

    public function adjust(User $user, int $quantity, string $idempotencyKey, string $reason): CreditLedgerEntry
    {
        return DB::transaction(function () use ($user, $quantity, $idempotencyKey, $reason): CreditLedgerEntry {
            $this->lockAccount($user);

            return $this->appendLocked($user, 'ADMIN_ADJUSTMENT', $quantity, $idempotencyKey, null, null, $reason);
        }, 3);
    }

    public function revokeUnusedPurchaseLocked(User $user, StorePurchase $purchase): ?CreditLedgerEntry
    {
        $key = 'purchase:'.$purchase->id.':revoked';
        if ($existing = CreditLedgerEntry::query()->where('idempotency_key', $key)->first()) {
            return $existing;
        }
        $granted = CreditLedgerEntry::query()->where('store_purchase_id', $purchase->id)->where('entry_type', 'PURCHASE_GRANTED')->exists();
        if (! $granted || $this->balance($user) < 1) {
            return null;
        }

        return $this->appendLocked($user, 'PURCHASE_REVOKED', -1, $key, $purchase, null, 'Store revoked or refunded an unused report credit');
    }

    private function appendLocked(User $user, string $type, int $quantity, string $idempotencyKey, ?StorePurchase $purchase, ?DiagnosticSession $diagnosis, string $reason): CreditLedgerEntry
    {
        $existing = CreditLedgerEntry::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing) {
            return $existing;
        }
        $balance = $this->balance($user);
        $after = $balance + $quantity;
        if ($after < 0) {
            throw new \DomainException('Insufficient report credit balance.');
        }

        return CreditLedgerEntry::query()->create([
            'user_id' => $user->id,
            'store_purchase_id' => $purchase?->id,
            'diagnostic_session_id' => $diagnosis?->id,
            'entry_type' => $type,
            'quantity' => $quantity,
            'balance_after' => $after,
            'idempotency_key' => $idempotencyKey,
            'reason' => $reason,
        ]);
    }

    private function lockAccount(User $user): BillingAccount
    {
        $account = $this->accounts->forUser($user);

        return BillingAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
    }
}
