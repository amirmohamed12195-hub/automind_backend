<?php

namespace App\DTO;

final readonly class VerifiedAppleNotification
{
    /** @param array<string, mixed> $payload */
    public function __construct(
        public string $notificationUuid,
        public string $notificationType,
        public ?string $subtype,
        public string $environment,
        public array $payload,
        public ?VerifiedStorePurchase $purchase,
    ) {}
}
