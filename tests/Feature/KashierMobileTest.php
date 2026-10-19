<?php

namespace Tests\Feature;

use App\Models\KashierOrder;
use App\Models\User;
use App\Services\PlatformSettings;
use Database\Seeders\BillingCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class KashierMobileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BillingCatalogSeeder::class);
        config(['billing.enabled' => true, 'kashier.enabled' => true, 'kashier.mobile_enabled' => true,
            'kashier.web_enabled' => false, 'kashier.mode' => 'test', 'kashier.merchant_id' => 'MID-TEST',
            'kashier.api_key' => 'private-key', 'kashier.secret_key' => 'private-secret',
            'kashier.prices' => ['SINGLE_FULL_REPORT' => '30.00', 'PLUS_MONTHLY' => '100.00', 'PLUS_YEARLY' => '1000.00']]);
        Http::preventStrayRequests();
    }

    public function test_mobile_catalog_requires_auth_and_only_enables_stored_egyptian_country(): void
    {
        $this->getJson('/api/v1/billing/kashier/catalog?platform=android')->assertUnauthorized();
        $user = User::factory()->create(['country_code' => 'SA']);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/billing/kashier/catalog?platform=android&countryCode=EG')
            ->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('data.plans', []);
        $this->postJson('/api/v1/billing/kashier/orders', $this->input() + ['countryCode' => 'EG'])->assertForbidden();
        $user->update(['country_code' => null]);
        $this->getJson('/api/v1/billing/kashier/catalog?platform=ios')->assertJsonPath('data.available', false);
        $user->update(['country_code' => 'EG']);
        foreach (['android', 'ios'] as $platform) {
            $this->getJson('/api/v1/billing/kashier/catalog?platform='.$platform)->assertOk()
                ->assertJsonPath('data.available', true)->assertJsonCount(3, 'data.plans')
                ->assertDontSee('private-key')->assertDontSee('private-secret');
        }
        $this->getJson('/api/v1/billing/kashier/catalog?platform=web')->assertUnprocessable();
        config(['kashier.mobile_enabled' => false]);
        $this->getJson('/api/v1/billing/kashier/catalog?platform=ios')->assertJsonPath('data.available', false);
        $this->postJson('/api/v1/billing/kashier/orders', $this->input())->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_mobile_checkout_is_idempotent_uses_server_prices_and_never_returns_keys(): void
    {
        $user = User::factory()->create(['country_code' => 'EG']);
        $this->actingAs($user, 'sanctum');
        Http::fake(['*/v3/payment/sessions' => Http::response(['_id' => 'mobile-session', 'sessionUrl' => 'https://payments.kashier.io/mobile-session'])]);
        $input = $this->input() + ['amount' => '0.01', 'currency' => 'USD', 'mode' => 'live'];
        $this->postJson('/api/v1/billing/kashier/orders', $input)->assertOk()
            ->assertJsonPath('data.amount', '30.00')->assertJsonPath('data.currency', 'EGP')
            ->assertJsonPath('data.mode', 'test')->assertJsonPath('data.sessionId', 'mobile-session')
            ->assertDontSee('private-key')->assertDontSee('private-secret')->assertDontSee('checkout_url');
        $this->postJson('/api/v1/billing/kashier/orders', $input)->assertOk();
        $this->getJson('/api/v1/billing/kashier/catalog?platform=android')->assertJsonPath('data.pendingOrder.sessionId', 'mobile-session');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('kashier_orders', 1);
        $this->assertDatabaseCount('store_purchases', 0);
    }

    public function test_status_is_owner_only_and_sdk_success_cannot_grant_access(): void
    {
        $order = $this->order();
        $other = User::factory()->create(['country_code' => 'EG']);
        $this->actingAs($other, 'sanctum')->getJson('/api/v1/billing/kashier/orders/'.$order->id)->assertNotFound();
        Http::fake(['*/v3/payment/sessions/*/payment' => Http::response(['data' => $this->payment($order, 'OPENED')])]);
        $this->actingAs($order->user, 'sanctum')->getJson('/api/v1/billing/kashier/orders/'.$order->id.'?status=SUCCESS&fulfilled=true')
            ->assertOk()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.fulfilled', false);
        $this->assertDatabaseCount('store_purchases', 0);
    }

    public function test_verified_payment_grants_once_even_when_country_changes_after_checkout(): void
    {
        $order = $this->order();
        Http::fake(['*/v3/payment/sessions/*/payment' => Http::response(['data' => $this->payment($order, 'PAID')])]);
        $order->user->update(['country_code' => 'SA']);
        for ($i = 0; $i < 2; $i++) {
            $this->actingAs($order->user, 'sanctum')->getJson('/api/v1/billing/kashier/orders/'.$order->id)
                ->assertOk()->assertJsonPath('data.status', 'paid')->assertJsonPath('data.fulfilled', true)
                ->assertJsonPath('data.sessionId', null)->assertJsonPath('data.entitlements.limits.reportCredits', 1);
        }
        $this->assertDatabaseCount('store_purchases', 1);
        $this->assertDatabaseCount('credit_ledger_entries', 1);
    }

    public function test_expired_unpaid_session_becomes_terminal_for_mobile_retry(): void
    {
        $order = $this->order();
        $order->update(['expires_at' => now()->subMinute()]);
        Http::fake(['*/v3/payment/sessions/*/payment' => Http::response(['data' => $this->payment($order, 'OPENED')])]);
        $this->actingAs($order->user, 'sanctum')->getJson('/api/v1/billing/kashier/orders/'.$order->id)
            ->assertOk()->assertJsonPath('data.status', 'expired')->assertJsonPath('data.sessionId', null);
    }

    public function test_website_checkout_is_disabled_but_return_page_and_webhook_remain(): void
    {
        $this->get('/billing')->assertRedirect(route('download'));
        $this->post('/billing/login', [])->assertNotFound();
        $this->post('/billing/checkout', $this->input())->assertNotFound();
        $this->get('/')->assertOk()->assertDontSee('href="'.route('billing.index').'"', false);
        $this->get('/billing/return/example?paymentStatus=SUCCESS')->assertOk()->assertSee('Return to AutoMind')->assertDontSee('Payment confirmed');
        $this->postJson('/api/v1/billing/webhooks/kashier', [])->assertUnauthorized();
    }

    private function input(): array
    {
        return ['platform' => 'android', 'plan' => 'SINGLE_FULL_REPORT', 'idempotencyKey' => (string) Str::uuid()];
    }

    public function test_dashboard_switch_is_authenticated_audited_and_enforced_for_new_orders(): void
    {
        $this->patch(route('admin.billing.kashier.update'), ['enabled' => false])
            ->assertRedirect(route('admin.login'));
        $adminSession = [config('admin.session_key') => true, 'automind_admin_username' => 'payment-admin'];
        $this->withSession($adminSession)->patch(route('admin.billing.kashier.update'), ['enabled' => false])
            ->assertRedirect(route('admin.dashboard').'#billing');
        $this->assertFalse((bool) app(PlatformSettings::class)->get('kashier_enabled'));
        $this->assertDatabaseHas('billing_admin_audit_logs', ['action' => 'billing.kashier.updated.web', 'admin_identifier' => 'payment-admin']);
        $user = User::factory()->create(['country_code' => 'EG']);
        $this->actingAs($user, 'sanctum')->getJson('/api/v1/billing/kashier/catalog?platform=android')
            ->assertOk()->assertJsonPath('data.available', false)->assertJsonPath('data.plans', []);
        $this->postJson('/api/v1/billing/kashier/orders', $this->input())->assertForbidden();
        $this->withSession($adminSession)->patch(route('admin.billing.kashier.update'), ['enabled' => true])
            ->assertRedirect(route('admin.dashboard').'#billing');
        $this->getJson('/api/v1/billing/kashier/catalog?platform=ios')->assertJsonPath('data.available', true);
        $this->withSession($adminSession)->get('/admin')->assertOk()->assertSee('kashier-enabled')->assertSee('Enable Kashier');
        Http::assertNothingSent();
    }

    public function test_disabling_checkout_keeps_existing_payments_verifiable_without_resuming_them(): void
    {
        $order = $this->order();
        $this->withSession([config('admin.session_key') => true, 'automind_admin_username' => 'admin'])
            ->patch(route('admin.billing.kashier.update'), ['enabled' => false])->assertRedirect();
        $this->getJson('/api/v1/billing/kashier/catalog?platform=android')->assertOk()
            ->assertJsonPath('data.available', false)->assertJsonPath('data.pendingOrder.id', $order->id)
            ->assertJsonPath('data.pendingOrder.sessionId', null);
        Http::fake(['*/v3/payment/sessions/*/payment' => Http::response(['data' => $this->payment($order, 'PAID')])]);
        $this->getJson('/api/v1/billing/kashier/orders/'.$order->id)->assertOk()
            ->assertJsonPath('data.fulfilled', true)->assertJsonPath('data.entitlements.limits.reportCredits', 1);
    }

    private function order(): KashierOrder
    {
        $user = User::factory()->create(['country_code' => 'EG']);
        Http::fake(['*/v3/payment/sessions' => Http::response(['_id' => 'mobile-session', 'sessionUrl' => 'https://payments.kashier.io/mobile-session'])]);
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/billing/kashier/orders', $this->input())->assertOk();

        return KashierOrder::query()->sole();
    }

    private function payment(KashierOrder $order, string $status): array
    {
        return ['sessionId' => $order->session_id, 'merchantId' => 'MID-TEST', 'status' => $status,
            'merchantOrderId' => $order->id, 'amount' => 30, 'currency' => 'EGP',
            'capturedAmount' => $status === 'PAID' ? 30 : 0, 'refundedAmount' => 0];
    }
}
