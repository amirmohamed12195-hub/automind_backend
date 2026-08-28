<?php

namespace Tests\Feature;

use App\Contracts\GooglePlayProvider;
use App\DTO\VerifiedStorePurchase;
use App\Jobs\ProcessBillingEvent;
use App\Jobs\ReconcileUserBilling;
use App\Models\BillingEvent;
use App\Models\BillingPlan;
use App\Models\DiagnosticSession;
use App\Models\StoreProduct;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Billing\BillingAccountService;
use App\Services\Billing\EntitlementService;
use App\Services\Billing\PurchaseVerificationService;
use App\Services\Billing\ReportEntitlementService;
use Carbon\CarbonImmutable;
use Database\Seeders\BillingCatalogSeeder;
use Illuminate\Support\Facades\Queue;
use Mockery;

class BillingApiTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BillingCatalogSeeder::class);
        config([
            'billing.enabled' => true,
            'billing.environment' => 'sandbox',
            'billing.platforms.apple' => true,
            'billing.platforms.google' => false,
        ]);
    }

    public function test_catalog_seeding_preserves_store_activation_state(): void
    {
        $product = StoreProduct::query()
            ->where('platform', 'apple')
            ->where('environment', 'sandbox')
            ->where('product_id', config('billing.products.plus_monthly.apple'))
            ->sole();
        $product->update(['active_for_sale' => true, 'store_status' => 'active']);

        $this->seed(BillingCatalogSeeder::class);

        $product->refresh();
        $this->assertTrue((bool) $product->active_for_sale);
        $this->assertSame('active', $product->store_status);
    }

    public function test_catalog_seeding_enables_configured_apple_products_for_storekit(): void
    {
        $products = StoreProduct::query()
            ->where('platform', 'apple')
            ->get();

        $this->assertCount(6, $products);
        foreach ($products as $product) {
            $this->assertTrue((bool) $product->active_for_sale);
            $this->assertSame('active', $product->store_status);
            $this->assertNotNull($product->last_synced_at);
        }
    }

    public function test_catalog_and_store_account_identifiers_are_server_driven(): void
    {
        $user = $this->actingAsUser(['country_code' => 'EG']);
        $this->getJson('/api/v1/billing/catalog?platform=google')
            ->assertOk()
            ->assertJsonPath('data.countryCode', 'EG')
            ->assertJsonPath('data.environment', 'sandbox')
            ->assertJsonCount(4, 'data.plans')
            ->assertJsonPath('data.plans.1.products.0.productId', 'automind_full_report_single_v1')
            ->assertJsonPath('data.plans.1.products.0.availableForSale', false);
        $response = $this->getJson('/api/v1/billing/account')->assertOk();
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $response->json('data.appleAppAccountToken'));
        $this->assertSame(64, strlen($response->json('data.googleObfuscatedAccountId')));
        $this->assertSame($user->id, $user->billingAccount()->firstOrFail()->user_id);
    }

    public function test_apple_catalog_is_enabled_without_enabling_google_billing(): void
    {
        $this->actingAsUser(['country_code' => 'EG']);

        $this->getJson('/api/v1/billing/catalog?platform=apple')
            ->assertOk()
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.plans.1.products.0.availableForSale', true);

        $this->getJson('/api/v1/billing/catalog?platform=google')
            ->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.plans.1.products.0.availableForSale', false);
    }

    public function test_store_account_identifier_needs_no_deployment_secret(): void
    {
        config(['app.env' => 'production']);
        $this->actingAsUser();

        $response = $this->getJson('/api/v1/billing/account')->assertOk();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{64}$/',
            $response->json('data.googleObfuscatedAccountId'),
        );
    }

    public function test_disabled_feature_flag_hides_active_store_products(): void
    {
        $this->actingAsUser(['country_code' => 'EG']);
        StoreProduct::query()
            ->where('platform', 'google')
            ->where('environment', 'sandbox')
            ->update(['active_for_sale' => true, 'store_status' => 'active']);
        config(['billing.enabled' => false]);

        $response = $this->getJson('/api/v1/billing/catalog?platform=google')
            ->assertOk()
            ->assertJsonPath('data.enabled', false);

        foreach ($response->json('data.plans') as $plan) {
            foreach ($plan['products'] as $product) {
                $this->assertFalse($product['availableForSale']);
            }
        }
    }

    public function test_duplicate_verified_consumable_grants_exactly_one_credit(): void
    {
        $user = $this->actingAsUser();
        $account = app(BillingAccountService::class)->forUser($user);
        $verified = $this->googleConsumable($account->google_obfuscated_account_id);
        $google = Mockery::mock(GooglePlayProvider::class);
        $google->shouldReceive('verifyPurchase')->twice()->andReturn($verified);
        $google->shouldReceive('consumeProduct')->twice();
        $this->app->instance(GooglePlayProvider::class, $google);
        $proof = ['purchaseToken' => 'google-token-one', 'productId' => 'automind_full_report_single_v1', 'environment' => 'sandbox'];

        $this->postJson('/api/v1/billing/purchases/google/verify', $proof)->assertOk()->assertJsonPath('data.planCode', 'SINGLE_FULL_REPORT');
        $this->postJson('/api/v1/billing/purchases/google/verify', $proof)->assertOk();

        $this->assertDatabaseCount('store_purchases', 1);
        $this->assertDatabaseCount('credit_ledger_entries', 1);
        $this->assertDatabaseHas('credit_ledger_entries', ['user_id' => $user->id, 'entry_type' => 'PURCHASE_GRANTED', 'quantity' => 1, 'balance_after' => 1]);
    }

    public function test_subscription_report_allowance_is_reserved_then_finalized_once(): void
    {
        $user = $this->actingAsUser();
        $account = app(BillingAccountService::class)->forUser($user);
        $verified = new VerifiedStorePurchase(
            'google', 'sandbox', 'automind_plus_v1', 'subscription', 'active', 'monthly-v1', null,
            null, null, 'subscription-token', 'GPA.1', $account->google_obfuscated_account_id,
            CarbonImmutable::now()->subDay(), CarbonImmutable::now()->subDay(), CarbonImmutable::now()->addMonth(),
            null, true, true, false,
        );
        app(PurchaseVerificationService::class)->record($user, $verified, 'test', true);
        $session = $this->diagnosticSession($user);
        $reports = app(ReportEntitlementService::class);

        $first = $reports->reserve($session);
        $second = $reports->reserve($session);
        $this->assertSame($first?->id, $second?->id);
        $this->assertDatabaseHas('entitlement_period_usage', ['reports_used' => 0, 'reports_reserved' => 1, 'report_limit' => 4]);

        $reports->finalize($session);
        $reports->finalize($session);
        $this->assertDatabaseHas('entitlement_period_usage', ['reports_used' => 1, 'reports_reserved' => 0]);
        $this->assertDatabaseHas('report_entitlement_reservations', ['diagnostic_session_id' => $session->id, 'status' => 'finalized']);
    }

    public function test_billing_retry_does_not_grant_subscription_access(): void
    {
        $user = $this->actingAsUser();
        $account = app(BillingAccountService::class)->forUser($user);
        $verified = new VerifiedStorePurchase(
            'google', 'sandbox', 'automind_plus_v1', 'subscription', 'billingRetry', 'monthly-v1', null,
            null, null, 'subscription-on-hold-token', 'GPA.on-hold', $account->google_obfuscated_account_id,
            CarbonImmutable::now()->subMonth(), CarbonImmutable::now()->subMonth(), CarbonImmutable::now()->addMonth(),
            null, false, true, false,
        );
        app(PurchaseVerificationService::class)->record($user, $verified, 'test', true);

        $snapshot = app(EntitlementService::class)->snapshot($user);

        $this->assertFalse($snapshot['access']['hasSubscription']);
        $this->assertSame('FREE', $snapshot['access']['planCode']);
    }

    public function test_google_voided_purchase_revokes_an_unused_credit_idempotently(): void
    {
        $user = $this->actingAsUser();
        $account = app(BillingAccountService::class)->forUser($user);
        $google = Mockery::mock(GooglePlayProvider::class);
        $google->shouldReceive('consumeProduct')->once();
        $this->app->instance(GooglePlayProvider::class, $google);
        $purchases = app(PurchaseVerificationService::class);
        $purchase = $purchases->record(
            $user,
            $this->googleConsumable($account->google_obfuscated_account_id),
            'test',
            true,
        );
        $event = BillingEvent::query()->create([
            'platform' => 'google',
            'external_event_id' => 'voided-event-1',
            'event_type' => 'VOIDED_PURCHASE_NOTIFICATION',
            'environment' => 'sandbox',
            'processing_status' => 'received',
            'received_at' => now(),
            'encrypted_payload_reference' => [
                'packageName' => config('billing.google.package_name'),
                'voidedPurchaseNotification' => [
                    'purchaseToken' => 'google-token-one',
                    'orderId' => 'GPA.2',
                    'productType' => 1,
                    'refundType' => 1,
                ],
            ],
        ]);

        app()->call([new ProcessBillingEvent($event->id), 'handle']);
        app()->call([new ProcessBillingEvent($event->id), 'handle']);

        $this->assertSame('refunded', $purchase->fresh()->state);
        $this->assertSame('processed', $event->fresh()->processing_status);
        $this->assertDatabaseHas('credit_ledger_entries', [
            'store_purchase_id' => $purchase->id,
            'entry_type' => 'PURCHASE_REVOKED',
            'quantity' => -1,
            'balance_after' => 0,
        ]);
        $this->assertDatabaseCount('credit_ledger_entries', 2);
    }

    public function test_google_voided_purchase_requires_review_when_credit_was_used(): void
    {
        $user = $this->actingAsUser();
        $account = app(BillingAccountService::class)->forUser($user);
        $google = Mockery::mock(GooglePlayProvider::class);
        $google->shouldReceive('consumeProduct')->once();
        $this->app->instance(GooglePlayProvider::class, $google);
        $purchase = app(PurchaseVerificationService::class)->record(
            $user,
            $this->googleConsumable($account->google_obfuscated_account_id),
            'test',
            true,
        );
        $session = $this->diagnosticSession($user);
        app(ReportEntitlementService::class)->reserve($session);
        app(ReportEntitlementService::class)->finalize($session);
        $event = BillingEvent::query()->create([
            'platform' => 'google',
            'external_event_id' => 'voided-used-credit-event',
            'event_type' => 'VOIDED_PURCHASE_NOTIFICATION',
            'environment' => 'sandbox',
            'processing_status' => 'received',
            'received_at' => now(),
            'encrypted_payload_reference' => [
                'packageName' => config('billing.google.package_name'),
                'voidedPurchaseNotification' => ['purchaseToken' => 'google-token-one'],
            ],
        ]);

        app()->call([new ProcessBillingEvent($event->id), 'handle']);

        $this->assertSame('refunded', $purchase->fresh()->state);
        $this->assertTrue((bool) data_get($purchase->fresh()->raw_reference, 'refundNeedsReview'));
        $this->assertSame('needs_review', $event->fresh()->processing_status);
        $this->assertDatabaseMissing('credit_ledger_entries', [
            'store_purchase_id' => $purchase->id,
            'entry_type' => 'PURCHASE_REVOKED',
        ]);
    }

    public function test_failed_credit_report_returns_credit_and_can_be_reserved_on_retry(): void
    {
        $user = $this->actingAsUser();
        $account = app(BillingAccountService::class)->forUser($user);
        app(PurchaseVerificationService::class)->record($user, $this->googleConsumable($account->google_obfuscated_account_id), 'test', true);
        $session = $this->diagnosticSession($user);
        $reports = app(ReportEntitlementService::class);

        $reports->reserve($session);
        $reports->release($session);
        $this->assertDatabaseHas('credit_ledger_entries', ['entry_type' => 'REPORT_RELEASED', 'balance_after' => 1]);
        $reports->reserve($session);
        $reports->finalize($session);

        $this->assertDatabaseHas('report_entitlement_reservations', ['diagnostic_session_id' => $session->id, 'status' => 'finalized']);
        $this->assertSame(0, (int) $user->creditLedgerEntries()->latest('id')->value('balance_after'));
        $this->assertDatabaseCount('credit_ledger_entries', 5);
    }

    public function test_purchase_with_wrong_store_account_is_rejected(): void
    {
        $this->actingAsUser();
        $google = Mockery::mock(GooglePlayProvider::class);
        $google->shouldReceive('verifyPurchase')->once()->andReturn($this->googleConsumable(str_repeat('a', 64)));
        $this->app->instance(GooglePlayProvider::class, $google);

        $this->postJson('/api/v1/billing/purchases/google/verify', ['purchaseToken' => 'google-token-one', 'productId' => 'automind_full_report_single_v1'])
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'PURCHASE_ACCOUNT_MISMATCH');
        $this->assertDatabaseCount('store_purchases', 0);
    }

    public function test_apple_notifications_are_deduplicated_before_queue_processing(): void
    {
        Queue::fake();
        $payload = $this->compactJws(['notificationUUID' => 'event-uuid', 'notificationType' => 'DID_RENEW', 'data' => ['environment' => 'Sandbox']]);

        $this->postJson('/api/v1/billing/webhooks/apple', ['signedPayload' => $payload])->assertStatus(202);
        $this->postJson('/api/v1/billing/webhooks/apple', ['signedPayload' => $payload])->assertStatus(202);

        $this->assertDatabaseCount('billing_events', 1);
        Queue::assertPushed(ProcessBillingEvent::class, 1);
    }

    public function test_billing_admin_permissions_are_role_specific(): void
    {
        $auditor = $this->actingAsUser(['is_admin' => true, 'admin_role' => 'AUDITOR']);
        $plan = BillingPlan::query()->where('code', 'FREE')->firstOrFail();
        $this->getJson('/api/v1/admin/billing/overview')->assertOk();
        $this->patchJson('/api/v1/admin/billing/plans/'.$plan->id, ['recommended' => true])->assertForbidden();

        $this->actingAsUser(['is_admin' => true, 'admin_role' => 'BILLING_ADMIN']);
        $this->patchJson('/api/v1/admin/billing/plans/'.$plan->id, ['recommended' => true])->assertOk();
        $this->assertDatabaseHas('billing_admin_audit_logs', ['action' => 'billing.plan.updated', 'resource_id' => $plan->id]);
    }

    public function test_billing_admin_lifecycle_endpoints_are_audited_and_safe(): void
    {
        Queue::fake();
        $customer = User::factory()->create();
        $this->actingAsUser(['is_admin' => true, 'admin_role' => 'BILLING_ADMIN']);

        $created = $this->postJson('/api/v1/admin/billing/plans', [
            'code' => 'PLUS_TRIAL_INTERNAL', 'type' => 'subscription', 'sortOrder' => 90,
        ])->assertCreated()->json('data');
        $this->getJson('/api/v1/admin/billing/plans/'.$created['id'])->assertOk();

        $source = BillingPlan::query()->where('code', 'PLUS_MONTHLY')->sole();
        $this->postJson('/api/v1/admin/billing/plans/'.$source->id.'/duplicate', ['code' => 'PLUS_MONTHLY_V2'])
            ->assertCreated()->assertJsonPath('data.active', false);
        $this->postJson('/api/v1/admin/billing/store-products', [
            'planId' => $created['id'], 'platform' => 'google', 'environment' => 'sandbox',
            'productId' => 'automind_plus_trial_v1', 'productType' => 'subscription', 'basePlanId' => 'trial-v1',
        ])->assertCreated()->assertJsonPath('data.store_status', 'pending')->assertJsonPath('data.active_for_sale', false);

        $grant = $this->postJson('/api/v1/admin/billing/users/'.$customer->id.'/grants', [
            'planCode' => 'PLUS_MONTHLY', 'endsAt' => now()->addDay()->toIso8601String(), 'reason' => 'Support test case',
        ])->assertCreated()->json('data');
        $this->deleteJson('/api/v1/admin/billing/users/'.$customer->id.'/grants/'.$grant['id'], ['reason' => 'Case resolved'])->assertOk();
        $this->postJson('/api/v1/admin/billing/users/'.$customer->id.'/reconcile')->assertStatus(202);
        Queue::assertPushed(ReconcileUserBilling::class, fn (ReconcileUserBilling $job) => $job->userId === (string) $customer->id);

        $this->getJson('/api/v1/admin/billing/subscriptions')->assertOk();
        $this->getJson('/api/v1/admin/billing/analytics')->assertOk()->assertJsonPath('data.financialNotice', 'Operational counts only. Revenue requires official store financial reports including fees and taxes.');
        $this->assertDatabaseHas('billing_admin_audit_logs', ['action' => 'billing.product.created']);
        $this->assertDatabaseHas('billing_admin_audit_logs', ['action' => 'billing.user.reconciliation_queued']);
    }

    private function googleConsumable(string $accountIdentifier): VerifiedStorePurchase
    {
        return new VerifiedStorePurchase(
            'google', 'sandbox', 'automind_full_report_single_v1', 'consumable', 'active', null, null,
            null, null, 'google-token-one', 'GPA.2', $accountIdentifier,
            CarbonImmutable::now(), null, null, null, false, true, true,
        );
    }

    private function diagnosticSession(User $user): DiagnosticSession
    {
        $vehicle = Vehicle::factory()->for($user)->create();

        return DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id]);
    }

    /** @param array<string, mixed> $payload */
    private function compactJws(array $payload): string
    {
        $encode = fn (array $value): string => rtrim(strtr(base64_encode((string) json_encode($value)), '+/', '-_'), '=');

        return $encode(['alg' => 'ES256']).'.'.$encode($payload).'.signature';
    }
}
