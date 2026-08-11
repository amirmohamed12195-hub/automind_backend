<?php

namespace App\Services\Billing;

use App\Contracts\AppleStoreProvider;
use App\DTO\VerifiedAppleNotification;
use App\DTO\VerifiedStorePurchase;
use App\Exceptions\BillingException;
use App\Models\StorePurchase;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AppleAppStoreProvider implements AppleStoreProvider
{
    public function __construct(private readonly AppleSignedDataVerifier $signedData) {}

    public function verifyPurchase(array $proof): VerifiedStorePurchase
    {
        $environment = $this->normalizeEnvironment($proof['environment'] ?? config('billing.environment'));
        $signed = is_string($proof['signedTransactionInfo'] ?? null) ? $proof['signedTransactionInfo'] : null;
        if (! $signed) {
            $transactionId = trim((string) ($proof['transactionId'] ?? ''));
            if ($transactionId === '') {
                throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'An Apple transaction identifier is required.');
            }
            $response = $this->request($environment)->get('/inApps/v1/transactions/'.rawurlencode($transactionId));
            if (! $response->successful() || ! is_string($response->json('signedTransactionInfo'))) {
                throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple could not confirm this transaction.', 422, $response->serverError());
            }
            $signed = $response->json('signedTransactionInfo');
        }

        return $this->purchaseFromPayload($this->signedData->verify($signed), $environment);
    }

    public function verifyNotification(string $signedPayload): VerifiedAppleNotification
    {
        $payload = $this->signedData->verify($signedPayload);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $environment = $this->normalizeEnvironment($data['environment'] ?? null);
        $this->validateApplication($data, $environment);
        $transaction = null;
        if (is_string($data['signedTransactionInfo'] ?? null)) {
            $transaction = $this->purchaseFromPayload($this->signedData->verify($data['signedTransactionInfo']), $environment);
        }
        $uuid = trim((string) ($payload['notificationUUID'] ?? ''));
        if ($uuid === '') {
            throw new BillingException('WEBHOOK_SIGNATURE_INVALID', 'Apple notification UUID is missing.', 400);
        }

        return new VerifiedAppleNotification(
            $uuid,
            (string) ($payload['notificationType'] ?? 'UNKNOWN'),
            isset($payload['subtype']) ? (string) $payload['subtype'] : null,
            $environment,
            $payload,
            $transaction,
        );
    }

    public function reconcile(StorePurchase $purchase): VerifiedStorePurchase
    {
        if (! $purchase->transaction_id) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple transaction identifier is unavailable.');
        }

        return $this->verifyPurchase(['transactionId' => $purchase->transaction_id, 'environment' => $purchase->environment]);
    }

    /** @param array<string, mixed> $payload */
    private function purchaseFromPayload(array $payload, string $environment): VerifiedStorePurchase
    {
        $this->validateApplication($payload, $environment);
        $transactionId = trim((string) ($payload['transactionId'] ?? ''));
        $originalId = trim((string) ($payload['originalTransactionId'] ?? ''));
        $productId = trim((string) ($payload['productId'] ?? ''));
        if ($transactionId === '' || $productId === '') {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple transaction fields are incomplete.');
        }
        $expiresAt = $this->milliseconds($payload['expiresDate'] ?? null);
        $revokedAt = $this->milliseconds($payload['revocationDate'] ?? null);
        $productType = str_contains(strtolower((string) ($payload['type'] ?? '')), 'subscription') ? 'subscription' : 'consumable';
        $state = $revokedAt ? 'revoked' : ($productType === 'subscription' && $expiresAt?->isPast() ? 'expired' : 'active');
        $autoRenew = false;
        $graceEnd = null;
        if ($productType === 'subscription' && $originalId !== '') {
            [$state, $autoRenew, $graceEnd] = $this->subscriptionStatus($originalId, $environment, $state);
        }

        return new VerifiedStorePurchase(
            'apple', $environment, $productId, $productType, $state,
            null, $payload['offerIdentifier'] ?? null, $transactionId, $originalId ?: $transactionId,
            null, null, isset($payload['appAccountToken']) ? strtolower((string) $payload['appAccountToken']) : null,
            $this->milliseconds($payload['purchaseDate'] ?? null), $this->milliseconds($payload['originalPurchaseDate'] ?? $payload['purchaseDate'] ?? null),
            $expiresAt, $graceEnd, $autoRenew, true, $productType === 'consumable',
            ['ownershipType' => $payload['inAppOwnershipType'] ?? null, 'transactionReason' => $payload['transactionReason'] ?? null, 'revocationDate' => $revokedAt?->toIso8601String()],
        );
    }

    /** @return array{0: string, 1: bool, 2: ?CarbonImmutable} */
    private function subscriptionStatus(string $originalId, string $environment, string $fallback): array
    {
        $response = $this->request($environment)->get('/inApps/v1/subscriptions/'.rawurlencode($originalId));
        if (! $response->successful()) {
            throw new BillingException(
                'STORE_UNAVAILABLE',
                'Apple subscription status could not be confirmed.',
                $response->status() === 404 ? 422 : 503,
                $response->serverError() || $response->status() === 429,
            );
        }
        $transactions = collect($response->json('data.0.lastTransactions', []));
        $transaction = $transactions->firstWhere('originalTransactionId', $originalId) ?? $transactions->first();
        if (! is_array($transaction)) {
            return [$fallback, false, null];
        }
        $status = match ((int) ($transaction['status'] ?? 0)) {
            1 => 'active', 2 => 'expired', 3 => 'billingRetry', 4 => 'gracePeriod', 5 => 'revoked', default => 'unknown',
        };
        $renewal = is_string($transaction['signedRenewalInfo'] ?? null)
            ? $this->signedData->verify($transaction['signedRenewalInfo'])
            : [];

        return [$status, (int) ($renewal['autoRenewStatus'] ?? 0) === 1, $this->milliseconds($renewal['gracePeriodExpiresDate'] ?? null)];
    }

    /** @param array<string, mixed> $payload */
    private function validateApplication(array $payload, string $environment): void
    {
        if (($payload['bundleId'] ?? null) !== config('billing.apple.bundle_id')) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple transaction bundle identifier does not match AutoMind.');
        }
        if (isset($payload['environment']) && $this->normalizeEnvironment($payload['environment']) !== $environment) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple transaction environment does not match the verification environment.');
        }
        if ($environment === 'production' && config('billing.apple.app_id') && (string) ($payload['appAppleId'] ?? '') !== (string) config('billing.apple.app_id')) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Apple application identifier does not match AutoMind.');
        }
    }

    private function request(string $environment): PendingRequest
    {
        $base = $environment === 'production' ? config('billing.apple.production_api_url') : config('billing.apple.sandbox_api_url');

        return Http::baseUrl((string) $base)->acceptJson()->withToken($this->apiToken())->timeout(15)->retry(2, 250, throw: false);
    }

    private function apiToken(): string
    {
        $issuer = trim((string) config('billing.apple.issuer_id'));
        $keyId = trim((string) config('billing.apple.key_id'));
        $key = trim((string) config('billing.apple.private_key'));
        $path = trim((string) config('billing.apple.private_key_path'));
        if ($key === '' && $path !== '' && is_file($path)) {
            $key = (string) file_get_contents($path);
        }
        $key = str_replace('\\n', "\n", $key);
        if ($issuer === '' || $keyId === '' || $key === '') {
            throw new BillingException('STORE_CREDENTIALS_MISSING', 'Apple App Store Server credentials are not configured.', 503);
        }
        $now = time();

        return JWT::encode([
            'iss' => $issuer, 'iat' => $now, 'exp' => $now + 1200,
            'aud' => 'appstoreconnect-v1', 'bid' => config('billing.apple.bundle_id'), 'nonce' => (string) Str::uuid(),
        ], $key, 'ES256', $keyId, ['typ' => 'JWT']);
    }

    private function normalizeEnvironment(mixed $environment): string
    {
        return strtolower((string) $environment) === 'production' ? 'production' : 'sandbox';
    }

    private function milliseconds(mixed $value): ?CarbonImmutable
    {
        return is_numeric($value) ? CarbonImmutable::createFromTimestampMs((int) $value) : null;
    }
}
