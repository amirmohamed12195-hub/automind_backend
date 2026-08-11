<?php

namespace App\Contracts;

use App\DTO\VerifiedStorePurchase;
use App\Models\StoreProduct;
use App\Models\StorePurchase;

interface GooglePlayProvider
{
    /** @param array<string, mixed> $proof */
    public function verifyPurchase(array $proof): VerifiedStorePurchase;

    public function reconcile(StorePurchase $purchase): VerifiedStorePurchase;

    public function acknowledgeSubscription(VerifiedStorePurchase $purchase): void;

    public function consumeProduct(VerifiedStorePurchase $purchase): void;

    /** @return array<int, array<string, mixed>> */
    public function syncProduct(StoreProduct $product): array;
}
