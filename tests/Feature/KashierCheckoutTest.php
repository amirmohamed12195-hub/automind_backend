<?php

namespace Tests\Feature;

use App\Exceptions\BillingException;
use App\Models\KashierOrder;
use App\Models\StorePurchase;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Services\Billing\CreditLedgerService;
use App\Services\Billing\KashierCheckoutService;
use App\Services\Billing\KashierClient;
use Database\Seeders\BillingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class KashierCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BillingCatalogSeeder::class);
        config(['billing.enabled' => true, 'kashier.enabled' => true, 'kashier.mode' => 'test',
            'kashier.merchant_id' => 'MID-TEST', 'kashier.api_key' => 'test-api-key', 'kashier.secret_key' => 'test-secret',
            'kashier.prices' => ['SINGLE_FULL_REPORT' => '30.00', 'PLUS_MONTHLY' => '100.00', 'PLUS_YEARLY' => '1000.00']]);
        Http::preventStrayRequests();
    }

    public function test_checkout_uses_server_price_and_creates_only_one_session_for_repeat_requests(): void
    {
        $user = User::factory()->create(['country_code' => 'EG']);
        Http::fake(['*/v3/payment/sessions' => Http::response(['_id' => 'session-1', 'sessionUrl' => 'https://payments.kashier.io/session-1?mode=test'])]);
        $key = (string) Str::uuid();
        $body = ['plan' => 'SINGLE_FULL_REPORT', 'idempotencyKey' => $key, 'amount' => '0.01', 'currency' => 'USD'];
        $this->withSession(['kashier_user_id' => $user->id])->post('/billing/checkout', $body)->assertRedirect('https://payments.kashier.io/session-1?mode=test');
        $this->post('/billing/checkout', $body)->assertRedirect();
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => $r['amount'] === '30.00' && $r['currency'] === 'EGP'
            && $r['customer']['reference'] === $user->id && $r->hasHeader('Authorization', 'test-secret')
            && $r->hasHeader('api-key', 'test-api-key') && $r['manualCapture'] === false
            && ! isset($r['mode'])
            && $r['serverWebhook'] === url('/api/v1/billing/webhooks/kashier'));
        $this->assertDatabaseCount('kashier_orders', 1);
        $this->assertDatabaseCount('store_purchases', 0);
    }

    public function test_checkout_requires_login_and_preserves_store_catalogs(): void
    {
        $this->post('/billing/checkout', ['plan' => 'PLUS_MONTHLY', 'idempotencyKey' => (string) Str::uuid()])->assertForbidden();
        $this->get('/billing')->assertOk()->assertSee('Sign in')->assertDontSee('test-secret')->assertDontSee('test-api-key');
        $user = User::factory()->create(['country_code' => 'EG']);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/billing/catalog?platform=apple')->assertOk()->assertJsonPath('data.platform', 'apple');
        $this->getJson('/api/v1/billing/catalog?platform=google')->assertOk()->assertJsonPath('data.platform', 'google');
    }

    public function test_bad_webhooks_and_incomplete_signed_fields_do_not_grant_access(): void
    {
        $order = $this->order();
        $payload = $this->payload($order);
        $this->postJson('/api/v1/billing/webhooks/kashier', $payload, ['x-kashier-signature' => str_repeat('0', 64)])->assertUnauthorized();
        $payload['data']['signatureKeys'] = ['status'];
        $this->postJson('/api/v1/billing/webhooks/kashier', $payload, ['x-kashier-signature' => $this->signature($payload)])->assertUnauthorized();
        Http::assertSentCount(1);
        $this->assertDatabaseCount('store_purchases', 0);
    }

    public function test_success_webhook_is_reconciled_and_duplicate_delivery_grants_one_credit(): void
    {
        $order = $this->order();
        $this->fakeSession($order, 'PAID');
        $payload = $this->payload($order);
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/billing/webhooks/kashier', $payload, ['x-kashier-signature' => $this->signature($payload)])->assertOk();
        }
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertSame(1, app(CreditLedgerService::class)->balance($order->user));
        $this->assertDatabaseCount('store_purchases', 1);
        $this->assertDatabaseCount('credit_ledger_entries', 1);
    }

    public function test_unsigned_event_type_or_forged_return_cannot_grant_access(): void
    {
        $order = $this->order();
        $this->fakeSession($order, 'CREATED');
        $payload = $this->payload($order);
        $payload['event'] = 'pay';
        $this->postJson('/api/v1/billing/webhooks/kashier', $payload, ['x-kashier-signature' => $this->signature($payload)])->assertOk();
        $this->get('/billing/return/'.$order->id.'?paymentStatus=SUCCESS&amount=30')->assertRedirect(route('billing.order', $order));
        $this->withSession(['kashier_user_id' => $order->user_id])->get('/billing/orders/'.$order->id)->assertOk()->assertSee('Awaiting payment');
        $this->assertDatabaseCount('store_purchases', 0);
    }

    public function test_paid_session_must_match_merchant_order_currency_and_amount(): void
    {
        $order = $this->order();
        foreach (['merchantId' => 'MID-OTHER', 'paymentParams.order' => 'other-order', 'paymentParams.amount' => 1, 'paymentParams.currency' => 'USD', '_id' => 'other-session'] as $key => $value) {
            $session = $this->providerSession($order, 'PAID');
            data_set($session, $key, $value);
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::swap(new Factory);
            Http::preventStrayRequests();
            Http::fake(['*/v3/payment/sessions/*' => Http::response($session)]);
            try {
                app(KashierCheckoutService::class)->refresh($order);
                $this->fail('Mismatched payment accepted: '.$key);
            } catch (BillingException $e) {
                $this->assertSame('KASHIER_ORDER_MISMATCH', $e->errorCode);
            }
        }
        $this->assertDatabaseCount('store_purchases', 0);
    }

    public function test_monthly_and_yearly_access_is_prepaid_and_expires_once(): void
    {
        foreach (['PLUS_MONTHLY' => 1, 'PLUS_YEARLY' => 12] as $code => $months) {
            $order = $this->order($code);
            $this->fakeSession($order, 'PAID');
            app(KashierCheckoutService::class)->refresh($order);
            $end = $order->fresh()->access_ends_at;
            $this->travel(2)->days();
            app(KashierCheckoutService::class)->refresh($order);
            $this->assertTrue($end->equalTo($order->fresh()->access_ends_at));
            $entitlement = UserEntitlement::query()->where('user_id', $order->user_id)->sole();
            $this->assertFalse($entitlement->auto_renew_enabled);
            $this->assertSame('kashier', $entitlement->source);
            $this->assertTrue($entitlement->period_end->equalTo($entitlement->period_start->copy()->addMonthsNoOverflow($months)));
            $this->travelBack();
        }
    }

    public function test_full_refund_is_idempotent_and_old_success_cannot_regrant(): void
    {
        $order = $this->order();
        $service = app(KashierCheckoutService::class);
        $this->fakeSession($order, 'PAID');
        $service->refresh($order);
        $this->fakeSession($order, 'REFUNDED', 30);
        $service->refresh($order);
        $service->refresh($order);
        $this->assertSame(0, app(CreditLedgerService::class)->balance($order->user));
        $this->assertDatabaseCount('credit_ledger_entries', 2);
        $this->fakeSession($order, 'PAID');
        $service->refresh($order);
        $this->assertSame('refunded', $order->fresh()->status);
        $this->assertSame('refunded', StorePurchase::query()->sole()->state);
        $this->assertSame(0, app(CreditLedgerService::class)->balance($order->user));
    }

    public function test_refund_revokes_subscription_and_partial_refund_keeps_existing_access(): void
    {
        $order = $this->order('PLUS_MONTHLY');
        $service = app(KashierCheckoutService::class);
        $this->fakeSession($order, 'PAID');
        $service->refresh($order);
        $this->fakeSession($order, 'PAID', 10);
        $service->refresh($order);
        $this->assertSame('partially_refunded', $order->fresh()->status);
        $this->assertSame('active', UserEntitlement::query()->sole()->status);
        $this->fakeSession($order, 'REFUNDED', 100);
        $service->refresh($order);
        $this->assertSame('refunded', UserEntitlement::query()->sole()->status);
    }

    public function test_test_payments_do_not_grant_production_benefits(): void
    {
        $order = $this->order();
        $this->fakeSession($order, 'PAID');
        $this->app->instance('env', 'production');
        app(KashierCheckoutService::class)->refresh($order);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertDatabaseCount('store_purchases', 0);
        $this->assertDatabaseCount('credit_ledger_entries', 0);
    }

    public function test_login_order_ownership_and_suspended_accounts(): void
    {
        $order = $this->order();
        $other = User::factory()->create(['password' => 'Password123!']);
        $this->post('/billing/login', ['email' => $other->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/billing/login', ['email' => $other->email, 'password' => 'Password123!'])->assertRedirect(route('billing.index', ['lang' => 'en']));
        $this->get('/billing/orders/'.$order->id)->assertNotFound();
        $other->update(['suspended_at' => now()]);
        $this->post('/billing/checkout', ['plan' => 'SINGLE_FULL_REPORT', 'idempotencyKey' => (string) Str::uuid()])->assertForbidden();
        Http::assertSentCount(1);
    }

    public function test_disabled_missing_price_hidden_plan_and_provider_errors_fail_closed(): void
    {
        $user = User::factory()->create(['country_code' => 'EG']);
        $this->withSession(['kashier_user_id' => $user->id]);
        $body = ['plan' => 'SINGLE_FULL_REPORT', 'idempotencyKey' => (string) Str::uuid()];
        config(['kashier.enabled' => false]);
        $this->post('/billing/checkout', $body)->assertSessionHasErrors('checkout');
        config(['kashier.enabled' => true, 'kashier.prices.SINGLE_FULL_REPORT' => null]);
        $this->post('/billing/checkout', $body)->assertSessionHasErrors('checkout');
        Http::assertNothingSent();
        config(['kashier.prices.SINGLE_FULL_REPORT' => '30.00']);
        Http::fake(['*/v3/payment/sessions' => Http::response(['secret' => 'do-not-leak'], 500)]);
        $this->post('/billing/checkout', $body)->assertSessionHasErrors('checkout');
        $this->assertSame('creation_failed', KashierOrder::query()->sole()->status);
        $this->assertDatabaseCount('store_purchases', 0);
    }

    public function test_signature_matches_the_official_rfc3986_test_vector(): void
    {
        config(['kashier.api_key' => '11111']);
        $data = ['amount' => 1, 'channel' => 'online | e-commerce', 'currency' => 'EGP', 'kashierOrderId' => '9ad06b17-755b-4e21-9774-aff3e2726ac9', 'merchantOrderId' => '1653481557813', 'method' => 'card', 'orderReference' => 'TEST-ORD-38855', 'status' => 'SUCCESS', 'transactionId' => 'TX-249893963', 'transactionResponseCode' => '00'];
        $data['signatureKeys'] = array_reverse(array_keys($data));
        $this->assertTrue(app(KashierClient::class)->verifyWebhook($data, '9610477b2255b2a8ef84fd89adfaa5f1305ff9c20324205851890f1ea03109f4'));
    }

    private function order(string $code = 'SINGLE_FULL_REPORT'): KashierOrder
    {
        $user = User::factory()->create(['country_code' => 'EG']);
        $id = 'session-'.Str::ulid();
        Http::fake(['*/v3/payment/sessions' => Http::response(['_id' => $id, 'sessionUrl' => 'https://payments.kashier.io/'.$id.'?mode=test'])]);

        return app(KashierCheckoutService::class)->create($user, $code, (string) Str::uuid());
    }

    private function providerSession(KashierOrder $order, string $status, int $refunded = 0): array
    {
        return ['_id' => $order->session_id, 'merchantId' => 'MID-TEST', 'status' => $status,
            'capturedAmount' => in_array($status, ['PAID', 'REFUNDED']) ? $order->amount_minor / 100 : 0,
            'refundedAmount' => $refunded,
            'paymentParams' => ['order' => $order->id, 'amount' => $order->amount_minor / 100, 'currency' => 'EGP']];
    }

    private function fakeSession(KashierOrder $order, string $status, int $refunded = 0): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake(['*/v3/payment/sessions/*' => Http::response($this->providerSession($order, $status, $refunded))]);
    }

    private function payload(KashierOrder $order): array
    {
        $data = ['amount' => $order->amount_minor / 100, 'currency' => 'EGP', 'merchantOrderId' => $order->id,
            'kashierOrderId' => 'provider-order', 'status' => 'SUCCESS', 'transactionId' => 'TX-123', 'transactionResponseCode' => '00'];
        $data['signatureKeys'] = array_keys($data);

        return ['event' => 'pay', 'data' => $data];
    }

    private function signature(array $payload): string
    {
        $data = array_intersect_key($payload['data'], array_flip($payload['data']['signatureKeys']));
        ksort($data);

        return hash_hmac('sha256', http_build_query($data, '', '&', PHP_QUERY_RFC3986), config('kashier.api_key'));
    }
}
