<?php

namespace App\Jobs;

use App\Contracts\AppleStoreProvider;
use App\Contracts\GooglePlayProvider;
use App\Exceptions\BillingException;
use App\Models\StorePurchase;
use App\Services\Billing\PurchaseVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ReconcileUserBilling implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 4;

    public int $timeout = 180;

    public function __construct(public readonly ?string $userId = null)
    {
        $this->onQueue('billing');
    }

    public function backoff(): array
    {
        return [30, 180, 900];
    }

    public function handle(AppleStoreProvider $apple, GooglePlayProvider $google, PurchaseVerificationService $purchases): void
    {
        $query = StorePurchase::query()
            ->with(['user', 'storeProduct'])
            ->whereNotNull('user_id')
            ->where(function ($q): void {
                $q->whereHas('storeProduct', fn ($product) => $product->where('product_type', 'subscription'))
                    ->orWhere(fn ($completion) => $completion->where('platform', 'google')->where(function ($state): void {
                        $state->where('acknowledged', false)->orWhere('consumed', false);
                    }));
            });
        if ($this->userId) {
            $query->where('user_id', $this->userId);
        } else {
            $query->where(fn ($stale) => $stale->whereNull('last_verified_at')->orWhere('last_verified_at', '<', now()->subHours(max(1, (int) config('billing.reconciliation.stale_hours')))));
        }

        $query->orderBy('id')->limit(max(1, (int) config('billing.reconciliation.batch_size')))->get()->each(function (StorePurchase $purchase) use ($apple, $google, $purchases): void {
            if (! $purchase->user) {
                return;
            }
            try {
                $verified = $purchase->platform === 'apple' ? $apple->reconcile($purchase) : $google->reconcile($purchase);
                $purchases->record($purchase->user, $verified, 'reconciliation');
            } catch (BillingException $e) {
                Log::warning('billing_reconciliation_failed', [
                    'purchase_id' => $purchase->id, 'platform' => $purchase->platform,
                    'error_code' => $e->errorCode, 'retryable' => $e->retryable,
                ]);
            }
        });
    }
}
