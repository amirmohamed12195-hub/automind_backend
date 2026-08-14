<?php

namespace App\Jobs;

use App\Contracts\AppleStoreProvider;
use App\Contracts\GooglePlayProvider;
use App\Exceptions\BillingException;
use App\Models\BillingEvent;
use App\Services\Billing\BillingAccountService;
use App\Services\Billing\PurchaseVerificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessBillingEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 6;

    public int $timeout = 120;

    public function __construct(public readonly string $eventId)
    {
        $this->onQueue('billing');
    }

    public function backoff(): array
    {
        return [10, 60, 300, 900, 3600];
    }

    public function handle(
        AppleStoreProvider $apple,
        GooglePlayProvider $google,
        BillingAccountService $accounts,
        PurchaseVerificationService $purchases,
    ): void {
        $event = BillingEvent::query()->findOrFail($this->eventId);
        if ($event->processing_status === 'processed') {
            return;
        }
        $event->update(['processing_status' => 'processing', 'attempts' => $event->attempts + 1, 'error_message' => null]);
        try {
            if ($event->platform === 'apple') {
                $signed = (string) ($event->encrypted_payload_reference['signedPayload'] ?? '');
                $notification = $apple->verifyNotification($signed);
                if (! $notification->purchase) {
                    $event->update(['processing_status' => 'ignored', 'processed_at' => now()]);

                    return;
                }
                $verified = $notification->purchase;
            } else {
                $payload = $event->encrypted_payload_reference;
                $voided = $payload['voidedPurchaseNotification'] ?? null;
                if (is_array($voided)) {
                    $purchase = $purchases->recordGoogleVoidedPurchase(
                        (string) ($voided['purchaseToken'] ?? ''),
                        $voided,
                    );
                    if (! $purchase) {
                        $event->update([
                            'processing_status' => 'needs_review',
                            'processed_at' => now(),
                            'error_message' => 'The voided purchase token does not match a verified purchase.',
                        ]);

                        return;
                    }
                    if ((bool) data_get($purchase->raw_reference, 'refundNeedsReview', false)) {
                        $event->update([
                            'processing_status' => 'needs_review',
                            'processed_at' => now(),
                            'error_message' => 'The refunded report credit was already used and requires manual review.',
                        ]);
                        Log::warning('billing_voided_purchase_requires_review', [
                            'platform' => 'google',
                            'event_id' => $event->id,
                            'purchase_id' => $purchase->id,
                        ]);

                        return;
                    }
                    $event->update(['processing_status' => 'processed', 'processed_at' => now()]);
                    Log::info('billing_voided_purchase_reconciled', [
                        'platform' => 'google',
                        'event_id' => $event->id,
                        'purchase_id' => $purchase->id,
                    ]);

                    return;
                }
                $subscription = $payload['subscriptionNotification'] ?? null;
                $oneTime = $payload['oneTimeProductNotification'] ?? null;
                if (! is_array($subscription) && ! is_array($oneTime)) {
                    $event->update(['processing_status' => 'needs_review', 'processed_at' => now(), 'error_message' => 'Notification type requires manual reconciliation.']);

                    return;
                }
                $notice = is_array($subscription) ? $subscription : $oneTime;
                $productId = (string) ($notice['subscriptionId'] ?? $notice['productId'] ?? $notice['sku'] ?? '');
                $verified = $google->verifyPurchase([
                    'purchaseToken' => $notice['purchaseToken'] ?? null,
                    'productId' => $productId,
                    'environment' => $event->environment,
                ]);
            }

            $account = $accounts->findByStoreIdentifier($verified->accountIdentifier);
            if (! $account || ! $account->user) {
                $event->update(['processing_status' => 'needs_review', 'processed_at' => now(), 'error_message' => 'No AutoMind account matches the store account identifier.']);

                return;
            }
            $purchases->record($account->user, $verified, 'webhook');
            $event->update(['processing_status' => 'processed', 'processed_at' => now()]);
            Log::info('billing_webhook_verified', ['platform' => $event->platform, 'event_id' => $event->id, 'event_type' => $event->event_type]);
        } catch (BillingException $e) {
            $event->update(['processing_status' => $e->retryable ? 'retrying' : 'needs_review', 'error_message' => mb_substr($e->getMessage(), 0, 1000)]);
            Log::warning('billing_webhook_failed', ['platform' => $event->platform, 'event_id' => $event->id, 'error_code' => $e->errorCode, 'retryable' => $e->retryable]);
            if ($e->retryable) {
                throw $e;
            }
        }
    }

    public function failed(?Throwable $exception): void
    {
        BillingEvent::query()->whereKey($this->eventId)->update([
            'processing_status' => 'failed',
            'error_message' => mb_substr($exception?->getMessage() ?? 'Billing event processing exhausted retries.', 0, 1000),
        ]);
    }
}
