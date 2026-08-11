<?php

namespace App\Contracts;

use App\DTO\VerifiedAppleNotification;
use App\DTO\VerifiedStorePurchase;
use App\Models\StorePurchase;

interface AppleStoreProvider
{
    /** @param array<string, mixed> $proof */
    public function verifyPurchase(array $proof): VerifiedStorePurchase;

    public function verifyNotification(string $signedPayload): VerifiedAppleNotification;

    public function reconcile(StorePurchase $purchase): VerifiedStorePurchase;
}
