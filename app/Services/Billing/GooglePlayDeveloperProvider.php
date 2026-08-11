<?php

namespace App\Services\Billing;

use App\Contracts\GooglePlayProvider;
use App\DTO\VerifiedStorePurchase;
use App\Exceptions\BillingException;
use App\Models\StoreProduct;
use App\Models\StorePurchase;
use Carbon\CarbonImmutable;
use Firebase\JWT\JWT;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class GooglePlayDeveloperProvider implements GooglePlayProvider
{
    public function verifyPurchase(array $proof): VerifiedStorePurchase
    {
        $token = trim((string) ($proof['purchaseToken'] ?? ''));
        if ($token === '') {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'A Google Play purchase token is required.');
        }
        $productId = trim((string) ($proof['productId'] ?? ''));
        $basePlanId = trim((string) ($proof['basePlanId'] ?? '')) ?: null;
        $environment = strtolower((string) ($proof['environment'] ?? config('billing.environment'))) === 'production' ? 'production' : 'sandbox';
        $mappingQuery = StoreProduct::query()
            ->where('platform', 'google')->where('environment', $environment)
            ->where('product_id', $productId);
        if ($basePlanId !== null) {
            $mappingQuery->where('base_plan_id', $basePlanId);
        }
        $mapping = $mappingQuery->orderByRaw("CASE WHEN product_type = 'subscription' THEN 0 ELSE 1 END")->first();
        if (! $mapping) {
            throw new BillingException('PRODUCT_NOT_CONFIGURED', 'This Google Play product is not configured.');
        }

        return $mapping->product_type === 'subscription'
            ? $this->subscription($token, $environment, $productId)
            : $this->oneTimeProduct($token, $environment, $productId);
    }

    public function reconcile(StorePurchase $purchase): VerifiedStorePurchase
    {
        if (! $purchase->purchase_token) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Google Play purchase token is unavailable.');
        }

        return $purchase->storeProduct?->product_type === 'subscription'
            ? $this->subscription($purchase->purchase_token, $purchase->environment, $purchase->product_id)
            : $this->oneTimeProduct($purchase->purchase_token, $purchase->environment, $purchase->product_id);
    }

    public function acknowledgeSubscription(VerifiedStorePurchase $purchase): void
    {
        if (! $purchase->purchaseToken || $purchase->acknowledged) {
            return;
        }
        $response = $this->request()->post(
            '/applications/'.rawurlencode($this->packageName()).'/purchases/subscriptions/'.rawurlencode($purchase->productId).'/tokens/'.rawurlencode($purchase->purchaseToken).':acknowledge',
            [],
        );
        if (! $response->successful()) {
            throw new BillingException('PURCHASE_ACKNOWLEDGEMENT_FAILED', 'Google Play subscription acknowledgement failed.', 503, true);
        }
    }

    public function consumeProduct(VerifiedStorePurchase $purchase): void
    {
        if (! $purchase->purchaseToken || $purchase->consumed) {
            return;
        }
        $response = $this->request()->post(
            '/applications/'.rawurlencode($this->packageName()).'/purchases/products/'.rawurlencode($purchase->productId).'/tokens/'.rawurlencode($purchase->purchaseToken).':consume',
            [],
        );
        if (! $response->successful()) {
            throw new BillingException('PURCHASE_CONSUMPTION_FAILED', 'Google Play product consumption failed.', 503, true);
        }
    }

    public function syncProduct(StoreProduct $product): array
    {
        $path = $product->product_type === 'subscription'
            ? '/applications/'.rawurlencode($this->packageName()).'/subscriptions/'.rawurlencode($product->product_id)
            : '/applications/'.rawurlencode($this->packageName()).'/oneTimeProducts/'.rawurlencode($product->product_id);
        $response = $this->request()->get($path);
        if ($response->status() === 404) {
            throw new BillingException('PRODUCT_NOT_FOUND', 'Google Play did not return the configured product.', 404);
        }
        if (! $response->successful()) {
            throw new BillingException('STORE_UNAVAILABLE', 'Google Play catalog synchronization failed.', 503, true);
        }
        $payload = $response->json();

        return is_array($payload) ? $this->regionalPrices($payload, $product) : [];
    }

    private function subscription(string $token, string $environment, string $expectedProductId): VerifiedStorePurchase
    {
        $response = $this->request()->get('/applications/'.rawurlencode($this->packageName()).'/purchases/subscriptionsv2/tokens/'.rawurlencode($token));
        if (! $response->successful()) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Google Play could not confirm this subscription.', 422, $response->serverError());
        }
        $payload = $response->json();
        $lineItems = is_array($payload['lineItems'] ?? null) ? $payload['lineItems'] : [];
        $line = collect($lineItems)->firstWhere('productId', $expectedProductId) ?? ($lineItems[0] ?? null);
        if (! is_array($line) || ($line['productId'] ?? null) !== $expectedProductId) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Google Play subscription product does not match the requested product.');
        }
        $state = match ($payload['subscriptionState'] ?? null) {
            'SUBSCRIPTION_STATE_PENDING' => 'pending',
            'SUBSCRIPTION_STATE_ACTIVE' => 'active',
            'SUBSCRIPTION_STATE_PAUSED' => 'paused',
            'SUBSCRIPTION_STATE_IN_GRACE_PERIOD' => 'gracePeriod',
            'SUBSCRIPTION_STATE_ON_HOLD' => 'billingRetry',
            'SUBSCRIPTION_STATE_CANCELED' => 'canceledActiveUntilExpiry',
            'SUBSCRIPTION_STATE_EXPIRED' => 'expired',
            default => 'unknown',
        };
        $expiresAt = $this->date($line['expiryTime'] ?? null);
        if ($state === 'canceledActiveUntilExpiry' && (! $expiresAt || $expiresAt->isPast())) {
            $state = 'expired';
        }
        $offer = is_array($line['offerDetails'] ?? null) ? $line['offerDetails'] : [];
        $external = is_array($payload['externalAccountIdentifiers'] ?? null) ? $payload['externalAccountIdentifiers'] : [];

        return new VerifiedStorePurchase(
            'google', $environment, (string) $line['productId'], 'subscription', $state,
            isset($offer['basePlanId']) ? (string) $offer['basePlanId'] : null,
            isset($offer['offerId']) ? (string) $offer['offerId'] : null,
            null, null, $token, isset($payload['latestOrderId']) ? (string) $payload['latestOrderId'] : null,
            isset($external['obfuscatedExternalAccountId']) ? (string) $external['obfuscatedExternalAccountId'] : null,
            $this->date($payload['startTime'] ?? null), $this->date($payload['startTime'] ?? null), $expiresAt,
            $state === 'gracePeriod' ? $expiresAt : null,
            (bool) data_get($line, 'autoRenewingPlan.autoRenewEnabled', false),
            ($payload['acknowledgementState'] ?? null) === 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED', false,
            ['linkedPurchaseToken' => $payload['linkedPurchaseToken'] ?? null, 'regionCode' => $payload['regionCode'] ?? null, 'testPurchase' => isset($payload['testPurchase'])],
        );
    }

    private function oneTimeProduct(string $token, string $environment, string $expectedProductId): VerifiedStorePurchase
    {
        $response = $this->request()->get('/applications/'.rawurlencode($this->packageName()).'/purchases/productsv2/tokens/'.rawurlencode($token));
        if (! $response->successful()) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Google Play could not confirm this product purchase.', 422, $response->serverError());
        }
        $payload = $response->json();
        $lineItems = is_array($payload['productLineItem'] ?? null) ? $payload['productLineItem'] : [];
        $line = collect($lineItems)->firstWhere('productId', $expectedProductId) ?? ($lineItems[0] ?? null);
        if (! is_array($line) || ($line['productId'] ?? null) !== $expectedProductId) {
            throw new BillingException('PURCHASE_VERIFICATION_FAILED', 'Google Play product does not match the requested product.');
        }
        $state = match (data_get($payload, 'purchaseStateContext.purchaseState')) {
            'PURCHASED' => 'active', 'PENDING' => 'pending', 'CANCELLED' => 'revoked', default => 'unknown',
        };
        $offer = is_array($line['productOfferDetails'] ?? null) ? $line['productOfferDetails'] : [];

        return new VerifiedStorePurchase(
            'google', $environment, (string) $line['productId'], 'consumable', $state,
            null, isset($offer['offerId']) ? (string) $offer['offerId'] : null,
            null, null, $token, isset($payload['orderId']) ? (string) $payload['orderId'] : null,
            isset($payload['obfuscatedExternalAccountId']) ? (string) $payload['obfuscatedExternalAccountId'] : null,
            $this->date($payload['purchaseCompletionTime'] ?? null), null, null, null, false,
            ($payload['acknowledgementState'] ?? null) === 'ACKNOWLEDGEMENT_STATE_ACKNOWLEDGED',
            ($offer['consumptionState'] ?? null) === 'CONSUMPTION_STATE_CONSUMED',
            ['regionCode' => $payload['regionCode'] ?? null, 'testPurchase' => isset($payload['testPurchaseContext'])],
        );
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl((string) config('billing.google.api_url'))->acceptJson()->withToken($this->accessToken())->timeout(15)->retry(2, 250, throw: false);
    }

    private function accessToken(): string
    {
        return Cache::remember('billing:google-play-access-token', now()->addMinutes(50), function (): string {
            $credentials = $this->credentials();
            $now = time();
            $assertion = JWT::encode([
                'iss' => $credentials['client_email'], 'scope' => 'https://www.googleapis.com/auth/androidpublisher',
                'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
            ], $credentials['private_key'], 'RS256');
            $response = Http::asForm()->timeout(15)->post($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $assertion,
            ]);
            $token = $response->json('access_token');
            if (! $response->successful() || ! is_string($token) || $token === '') {
                throw new BillingException('STORE_CREDENTIALS_INVALID', 'Google Play service-account authentication failed.', 503);
            }

            return $token;
        });
    }

    /** @return array{client_email: string, private_key: string, token_uri?: string} */
    private function credentials(): array
    {
        $json = trim((string) config('billing.google.service_account'));
        $path = trim((string) config('billing.google.service_account_path'));
        if ($json === '' && $path !== '' && is_file($path)) {
            $json = (string) file_get_contents($path);
        }
        $decoded = json_decode($json, true);
        if (! is_array($decoded) || ! is_string($decoded['client_email'] ?? null) || ! is_string($decoded['private_key'] ?? null)) {
            throw new BillingException('STORE_CREDENTIALS_MISSING', 'Google Play service-account credentials are not configured.', 503);
        }

        return $decoded;
    }

    private function packageName(): string
    {
        $package = trim((string) config('billing.google.package_name'));
        if ($package === '') {
            throw new BillingException('STORE_CREDENTIALS_MISSING', 'Google Play package name is not configured.', 503);
        }

        return $package;
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        try {
            return is_string($value) && $value !== '' ? CarbonImmutable::parse($value)->utc() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $payload
     * @return array<int, array<string, mixed>>
     */
    private function regionalPrices(array $payload, StoreProduct $product): array
    {
        $regions = [];
        $configs = $product->product_type === 'subscription'
            ? collect($payload['basePlans'] ?? [])->where('basePlanId', $product->base_plan_id)->flatMap(fn (array $plan) => $plan['regionalConfigs'] ?? [])
            : collect($payload['purchaseOptions'] ?? [])->flatMap(fn (array $option) => $option['regionalPricingAndAvailabilityConfigs'] ?? []);
        foreach ($configs as $configuration) {
            $price = $configuration['price'] ?? null;
            if (! is_array($price) || ! isset($price['currencyCode'])) {
                continue;
            }
            $units = (string) ($price['units'] ?? '0');
            $nanos = (int) ($price['nanos'] ?? 0);
            $amount = bcadd($units, bcdiv((string) $nanos, '1000000000', 6), 6);
            $regions[] = [
                'countryCode' => $configuration['regionCode'] ?? null,
                'currency' => $price['currencyCode'], 'customerPrice' => $amount,
                'formattedPrice' => $amount.' '.$price['currencyCode'],
                'billingPeriod' => data_get($payload, 'basePlans.0.autoRenewingBasePlanType.billingPeriodDuration'),
            ];
        }

        return $regions;
    }
}
