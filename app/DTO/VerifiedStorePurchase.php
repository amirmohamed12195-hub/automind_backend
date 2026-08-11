<?php

namespace App\DTO;

use Carbon\CarbonImmutable;

final readonly class VerifiedStorePurchase
{
    /** @param array<string, mixed> $rawReference */
    public function __construct(
        public string $platform,
        public string $environment,
        public string $productId,
        public string $productType,
        public string $state,
        public ?string $basePlanId,
        public ?string $offerId,
        public ?string $transactionId,
        public ?string $originalTransactionId,
        public ?string $purchaseToken,
        public ?string $orderId,
        public ?string $accountIdentifier,
        public ?CarbonImmutable $purchasedAt,
        public ?CarbonImmutable $periodStart,
        public ?CarbonImmutable $expiresAt,
        public ?CarbonImmutable $gracePeriodEnd,
        public bool $autoRenewEnabled,
        public bool $acknowledged,
        public bool $consumed,
        public array $rawReference = [],
    ) {}

    public function uniqueStoreKey(): string
    {
        if ($this->platform === 'apple' && $this->transactionId) {
            return "apple:{$this->environment}:{$this->transactionId}";
        }
        if ($this->platform === 'google' && $this->purchaseToken) {
            return "google:{$this->environment}:".hash('sha256', $this->purchaseToken);
        }

        throw new \LogicException('A verified store purchase has no unique store identifier.');
    }
}
