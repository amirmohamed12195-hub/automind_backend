<?php

namespace App\Services\Billing;

use App\Contracts\AppleStoreProvider;
use App\Contracts\GooglePlayProvider;
use App\DTO\VerifiedStorePurchase;
use App\Exceptions\BillingException;
use App\Models\BillingAccount;
use App\Models\StoreProduct;
use App\Models\StorePurchase;
use App\Models\User;
use App\Models\UserEntitlement;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseVerificationService
{
    public function __construct(
        private readonly AppleStoreProvider $apple,
        private readonly GooglePlayProvider $google,
        private readonly BillingAccountService $accounts,
        private readonly CreditLedgerService $credits,
    ) {}

    /** @param array<string, mixed> $proof */
    public function verifyForUser(User $user, string $platform, array $proof, string $source = 'client'): StorePurchase
    {
        $verified = match ($platform) {
            'apple' => $this->apple->verifyPurchase($proof),
            'google' => $this->google->verifyPurchase($proof),
            default => throw new BillingException('PLATFORM_NOT_SUPPORTED', 'The purchase platform is not supported.'),
        };

        return $this->record($user, $verified, $source, true);
    }

    /**
     * Reconcile a Google Real-time Developer Notification for a voided
     * purchase without trusting product or account data from the webhook.
     * The original verified purchase remains the source of truth.
     *
     * @param  array<string, mixed>  $notification
     */
    public function recordGoogleVoidedPurchase(string $purchaseToken, array $notification = []): ?StorePurchase
    {
        if (trim($purchaseToken) === '') {
            return null;
        }

        $purchase = StorePurchase::query()
            ->with(['user', 'storeProduct'])
            ->where('platform', 'google')
            ->where('purchase_token_hash', hash('sha256', $purchaseToken))
            ->first();
        if (! $purchase?->user || ! $purchase->storeProduct) {
            return null;
        }

        $account = $this->accounts->forUser($purchase->user);
        $rawReference = is_array($purchase->raw_reference) ? $purchase->raw_reference : [];
        $verified = new VerifiedStorePurchase(
            platform: 'google',
            environment: $purchase->environment,
            productId: $purchase->product_id,
            productType: $purchase->storeProduct->product_type,
            state: 'refunded',
            basePlanId: $purchase->base_plan_id,
            offerId: $purchase->offer_id,
            transactionId: $purchase->transaction_id,
            originalTransactionId: $purchase->original_transaction_id,
            purchaseToken: $purchase->purchase_token,
            orderId: $purchase->order_id,
            accountIdentifier: $account->google_obfuscated_account_id,
            purchasedAt: $purchase->purchased_at ? CarbonImmutable::instance($purchase->purchased_at) : null,
            periodStart: $purchase->purchased_at ? CarbonImmutable::instance($purchase->purchased_at) : null,
            expiresAt: $purchase->expires_at ? CarbonImmutable::instance($purchase->expires_at) : null,
            gracePeriodEnd: null,
            autoRenewEnabled: false,
            acknowledged: (bool) $purchase->acknowledged,
            consumed: (bool) $purchase->consumed,
            rawReference: [...$rawReference, 'voidedPurchaseNotification' => $notification],
        );

        return $this->record($purchase->user, $verified, 'voided_notification');
    }

    public function record(User $user, VerifiedStorePurchase $verified, string $source = 'server', bool $requireAccountLink = false): StorePurchase
    {
        $this->validateEnvironment($verified);
        $account = $this->accounts->forUser($user);
        $expectedAccount = $verified->platform === 'apple'
            ? strtolower($account->apple_app_account_token)
            : $account->google_obfuscated_account_id;
        $actualAccount = $verified->accountIdentifier ? strtolower($verified->accountIdentifier) : null;
        if (($requireAccountLink && ! $actualAccount) || ($actualAccount && ! hash_equals(strtolower($expectedAccount), $actualAccount))) {
            throw new BillingException('PURCHASE_ACCOUNT_MISMATCH', 'This store purchase is not linked to the signed-in AutoMind account.', 409);
        }

        $mapping = $this->mapping($verified);
        $purchase = DB::transaction(function () use ($user, $account, $verified, $mapping, $source): StorePurchase {
            BillingAccount::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
            $query = StorePurchase::query()->where('platform', $verified->platform)->where('environment', $verified->environment);
            if ($verified->platform === 'apple') {
                $query->where('transaction_id', $verified->transactionId);
            } else {
                $query->where('purchase_token_hash', hash('sha256', (string) $verified->purchaseToken));
            }
            $purchase = $query->lockForUpdate()->first();
            if ($purchase && $purchase->user_id && $purchase->user_id !== $user->id) {
                throw new BillingException('PURCHASE_ALREADY_CLAIMED', 'This purchase belongs to another AutoMind account.', 409);
            }
            $values = [
                'user_id' => $user->id,
                'store_product_id' => $mapping->id,
                'platform' => $verified->platform,
                'environment' => $verified->environment,
                'product_id' => $verified->productId,
                'base_plan_id' => $verified->basePlanId,
                'offer_id' => $verified->offerId,
                'transaction_id' => $verified->transactionId,
                'original_transaction_id' => $verified->originalTransactionId,
                'purchase_token' => $verified->purchaseToken,
                'purchase_token_hash' => $verified->purchaseToken ? hash('sha256', $verified->purchaseToken) : null,
                'order_id' => $verified->orderId,
                'state' => $verified->state,
                'acknowledged' => $verified->acknowledged,
                'consumed' => $verified->consumed,
                'purchased_at' => $verified->purchasedAt,
                'expires_at' => $verified->expiresAt,
                'raw_reference' => [...$verified->rawReference, 'verificationSource' => $source],
                'last_verified_at' => now(),
            ];
            if ($purchase) {
                $purchase->update($values);
            } else {
                $purchase = StorePurchase::query()->create($values);
            }

            if ($verified->productType === 'subscription') {
                $this->upsertSubscription($user, $purchase, $mapping, $verified, $source);
            } elseif ($verified->state === 'active') {
                $this->credits->grantPurchase($user, $purchase);
            } elseif (in_array($verified->state, ['revoked', 'refunded'], true)) {
                $reversal = $this->credits->revokeUnusedPurchaseLocked($user, $purchase);
                if (! $reversal) {
                    $references = $purchase->raw_reference ?? [];
                    $purchase->update(['raw_reference' => [...$references, 'refundNeedsReview' => true]]);
                }
            }

            return $purchase->fresh(['storeProduct.plan']);
        }, 3);

        $this->completeGooglePurchase($purchase, $verified);

        Log::info('billing_purchase_verified', [
            'platform' => $verified->platform,
            'environment' => $verified->environment,
            'product_id' => $verified->productId,
            'state' => $verified->state,
            'purchase_id' => $purchase->id,
            'user_id_hash' => hash_hmac('sha256', (string) $user->id, (string) config('app.key')),
        ]);

        return $purchase->fresh(['storeProduct.plan']);
    }

    private function upsertSubscription(User $user, StorePurchase $purchase, StoreProduct $mapping, VerifiedStorePurchase $verified, string $source): void
    {
        $storeIdentity = $verified->platform === 'apple'
            ? $verified->originalTransactionId
            : hash('sha256', (string) $verified->purchaseToken);
        if (! $storeIdentity) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'The subscription identity is missing.');
        }
        $key = implode(':', [$verified->platform, $verified->environment, $storeIdentity]);
        $entitlement = UserEntitlement::query()->where('entitlement_key', $key)->lockForUpdate()->first();
        if ($entitlement && $entitlement->user_id !== $user->id) {
            throw new BillingException('PURCHASE_ALREADY_CLAIMED', 'This subscription belongs to another AutoMind account.', 409);
        }
        $values = [
            'user_id' => $user->id,
            'billing_plan_id' => $mapping->billing_plan_id,
            'store_purchase_id' => $purchase->id,
            'entitlement_key' => $key,
            'source' => 'store',
            'platform' => $verified->platform,
            'status' => $verified->state,
            'purchase_date' => $verified->purchasedAt,
            'period_start' => $verified->periodStart ?? $verified->purchasedAt,
            'period_end' => $verified->expiresAt,
            'auto_renew_enabled' => $verified->autoRenewEnabled,
            'grace_period_end' => $verified->gracePeriodEnd,
            'canceled_at' => $verified->state === 'canceledActiveUntilExpiry' ? now() : null,
            'revoked_at' => $verified->state === 'revoked' ? now() : null,
            'refunded_at' => $verified->state === 'refunded' ? now() : null,
            'last_verified_at' => now(),
            'verification_source' => $source,
        ];
        if ($entitlement) {
            $entitlement->update($values);
        } else {
            UserEntitlement::query()->create($values);
        }

        $linkedToken = $verified->rawReference['linkedPurchaseToken'] ?? null;
        if ($verified->platform === 'google' && is_string($linkedToken) && $linkedToken !== '') {
            $old = StorePurchase::query()->where('purchase_token_hash', hash('sha256', $linkedToken))->first();
            if ($old) {
                UserEntitlement::query()->where('store_purchase_id', $old->id)->where('user_id', $user->id)->update([
                    'status' => 'replaced', 'auto_renew_enabled' => false, 'last_verified_at' => now(),
                ]);
            }
        }
    }

    private function mapping(VerifiedStorePurchase $verified): StoreProduct
    {
        $query = StoreProduct::query()
            ->where('platform', $verified->platform)
            ->where('environment', $verified->environment)
            ->where('product_id', $verified->productId)
            ->where('product_type', $verified->productType);
        if ($verified->platform === 'google' && $verified->productType === 'subscription') {
            $query->where('base_plan_id', $verified->basePlanId);
        }
        if ($verified->platform === 'google') {
            $query->where('offer_id', $verified->offerId);
        }
        $mapping = $query->first();
        if (! $mapping) {
            throw new BillingException('PRODUCT_NOT_CONFIGURED', 'The verified store product is not mapped to a billing plan.');
        }

        return $mapping;
    }

    private function validateEnvironment(VerifiedStorePurchase $verified): void
    {
        $expected = strtolower((string) config('billing.environment')) === 'production' ? 'production' : 'sandbox';
        if ($verified->environment !== $expected) {
            throw new BillingException('PURCHASE_ENVIRONMENT_MISMATCH', 'The purchase belongs to a different store environment.', 409);
        }
    }

    private function completeGooglePurchase(StorePurchase $purchase, VerifiedStorePurchase $verified): void
    {
        if ($verified->platform !== 'google' || $verified->state !== 'active') {
            return;
        }
        try {
            if ($verified->productType === 'subscription') {
                $this->google->acknowledgeSubscription($verified);
                $purchase->update(['acknowledged' => true]);
            } else {
                $this->google->consumeProduct($verified);
                $purchase->update(['consumed' => true]);
            }
        } catch (BillingException $e) {
            Log::warning('billing_store_completion_pending', [
                'purchase_id' => $purchase->id, 'platform' => 'google', 'error_code' => $e->errorCode,
            ]);
        }
    }
}
