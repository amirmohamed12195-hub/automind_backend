<?php

namespace Tests\Unit;

use App\Services\Billing\AppleAppStoreProvider;
use App\Services\Billing\AppleSignedDataVerifier;
use Mockery;
use Tests\TestCase;

class AppleAppStoreProviderTest extends TestCase
{
    public function test_signed_transaction_environment_overrides_production_catalog_environment(): void
    {
        config([
            'billing.environment' => 'production',
            'billing.apple.bundle_id' => 'com.automind.ai',
        ]);
        $signedData = Mockery::mock(AppleSignedDataVerifier::class);
        $signedData->shouldReceive('verify')->once()->with('signed-transaction')->andReturn([
            'bundleId' => 'com.automind.ai',
            'environment' => 'Sandbox',
            'transactionId' => '2000000123456789',
            'originalTransactionId' => '2000000123456789',
            'productId' => 'com.automind.ai.full_report.single.v1',
            'type' => 'Consumable',
            'purchaseDate' => now()->getTimestampMs(),
        ]);

        $purchase = (new AppleAppStoreProvider($signedData))->verifyPurchase([
            'signedTransactionInfo' => 'signed-transaction',
        ]);

        $this->assertSame('sandbox', $purchase->environment);
        $this->assertSame('com.automind.ai.full_report.single.v1', $purchase->productId);
        $this->assertSame('active', $purchase->state);
    }
}
