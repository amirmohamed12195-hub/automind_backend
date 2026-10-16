<?php

namespace Tests\Feature;

use App\Contracts\ObjectStorageProvider;
use App\Jobs\PurgeExpiredData;
use App\Models\AppleAuthorization;
use App\Models\User;
use App\Services\AccountDeletionService;
use App\Services\Auth\AppleAuthorizationService;
use App\Services\Auth\SocialIdentityVerifier;
use App\Services\Storage\LaravelObjectStorageProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;

class AppleAuthorizationDeletionTest extends ApiTestCase
{
    private string $keyPath;

    protected function setUp(): void
    {
        parent::setUp();
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        openssl_pkey_export($key, $pem);
        $this->keyPath = tempnam(sys_get_temp_dir(), 'apple-test-');
        file_put_contents($this->keyPath, $pem);
        config(['services.apple.team_id' => 'TESTTEAM', 'services.apple.sign_in_key_id' => 'TESTKEY', 'services.apple.sign_in_private_key_path' => $this->keyPath]);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        unlink($this->keyPath);
        parent::tearDown();
    }

    public function test_login_exchanges_code_and_stores_an_encrypted_hidden_token(): void
    {
        $identity = ['subject' => 'apple-sub', 'audience' => 'com.automind.ai', 'email' => 'apple@example.test', 'name' => null];
        $this->mock(SocialIdentityVerifier::class, function ($mock) use ($identity): void {
            $mock->shouldReceive('verify')->with('apple', 'initial', 'nonce')->once()->andReturn($identity);
            $mock->shouldReceive('verify')->with('apple', 'exchanged', 'nonce')->once()->andReturn($identity);
        });
        Http::fake(['appleid.apple.com/auth/token' => Http::response(['id_token' => 'exchanged', 'refresh_token' => 'private-refresh'])]);
        $this->postJson('/api/v1/auth/social/apple', ['identityToken' => 'initial', 'nonce' => 'nonce', 'authorizationCode' => 'one-use-code', 'termsAccepted' => true, 'privacyAccepted' => true, 'legalVersion' => config('public.effective_date')])->assertOk()->assertDontSee('private-refresh');
        $grant = AppleAuthorization::query()->sole();
        $this->assertSame('private-refresh', $grant->refresh_token);
        $this->assertNotSame('private-refresh', DB::table('apple_authorizations')->value('refresh_token'));
        $this->assertArrayNotHasKey('refresh_token', $grant->toArray());
        Http::assertSent(fn ($request) => $request['code'] === 'one-use-code' && ! isset($request['redirect_uri']));
    }

    public function test_exchanged_identity_must_match_the_login_and_web_redirect_is_server_selected(): void
    {
        $this->mock(SocialIdentityVerifier::class, function ($mock): void {
            $mock->shouldReceive('verify')->andReturn(['subject' => 'other-user', 'audience' => 'com.automind.ai.service']);
        });
        Http::fake(['appleid.apple.com/auth/token' => Http::response(['id_token' => 'exchanged', 'refresh_token' => 'private-refresh'])]);
        try {
            app(AppleAuthorizationService::class)->exchange('code', 'nonce', ['subject' => 'expected-user', 'audience' => 'com.automind.ai.service']);
            $this->fail('Mismatched authorization must fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Apple authorization identity mismatch.', $exception->getMessage());
        }
        Http::assertSent(fn ($request) => $request['redirect_uri'] === config('services.apple.redirect_uri'));
        $this->assertDatabaseCount('apple_authorizations', 0);
    }

    public function test_deletion_disables_account_even_during_apple_outage_and_retries_without_user_data(): void
    {
        $user = $this->actingAsUser();
        $grant = AppleAuthorization::query()->create(['user_id' => $user->id, 'client_id' => 'com.automind.ai', 'refresh_token' => 'private-refresh']);
        Http::fake(['appleid.apple.com/auth/revoke' => Http::sequence()->push([], 503)->push('', 200)]);
        $this->deleteJson('/api/v1/me')->assertNoContent();
        $this->assertSoftDeleted($user);
        $grant->refresh();
        $this->assertNull($grant->user_id);
        $this->assertSame(1, $grant->attempts);
        app(AppleAuthorizationService::class)->revokePending();
        Http::assertSentCount(1);
        $this->travel(16)->minutes();
        app(AppleAuthorizationService::class)->revokePending();
        Http::assertSentCount(2);
        $this->assertDatabaseMissing('apple_authorizations', ['id' => $grant->id]);
    }

    public function test_legacy_account_deletion_does_not_require_an_apple_token(): void
    {
        $user = $this->actingAsUser();
        $this->deleteJson('/api/v1/me')->assertNoContent();
        $this->assertSoftDeleted($user);
        Http::assertNothingSent();
    }

    public function test_retention_removes_avatar_before_purging_account_and_retries_storage_failure(): void
    {
        $user = User::factory()->create(['avatar_path' => 'avatars/test.jpg', 'deletion_requested_at' => now()->subDays(40)]);
        $user->delete();
        $storage = Mockery::mock(ObjectStorageProvider::class);
        $storage->shouldReceive('delete')->once()->andThrow(new RuntimeException('Storage unavailable'));
        $this->app->instance(ObjectStorageProvider::class, $storage);
        try {
            app(AccountDeletionService::class)->purge($user);
            $this->fail('Storage failure must keep references for retry.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('users', ['id' => $user->id, 'avatar_path' => 'avatars/test.jpg']);
        }
        $this->app->forgetInstance(ObjectStorageProvider::class);
        $this->app->bind(ObjectStorageProvider::class, LaravelObjectStorageProvider::class);
        Storage::fake(config('automind.media.disk'));
        Storage::disk(config('automind.media.disk'))->put('avatars/test.jpg', 'test');
        (new PurgeExpiredData)->handle();
        Storage::disk(config('automind.media.disk'))->assertMissing('avatars/test.jpg');
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
