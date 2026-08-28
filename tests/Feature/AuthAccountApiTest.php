<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\DiagnosticSession;
use App\Models\SocialIdentity;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Auth\SocialIdentityVerifier;
use Illuminate\Support\Facades\Hash;
use Mockery;

class AuthAccountApiTest extends ApiTestCase
{
    public function test_registration_login_me_settings_and_logout_contract(): void
    {
        $register = $this->postJson('/api/v1/auth/register', ['name' => 'Driver', 'email' => ' DRIVER@EXAMPLE.COM ', 'phone' => '+201001234567', 'countryCode' => 'eg', 'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'deviceName' => 'iPhone', 'locale' => 'en', 'termsAccepted' => true, 'privacyAccepted' => true, 'legalVersion' => '2026-08-11']);
        $register->assertCreated()
            ->assertJsonPath('data.verificationRequired', true)
            ->assertJsonPath('data.purpose', 'registration')
            ->assertJsonPath('data.maskedPhone', '+20••••••4567')
            ->assertJsonStructure(['data' => ['verificationToken', 'expiresInSeconds', 'resendAfterSeconds'], 'meta' => ['requestId']])
            ->assertHeader('X-Request-Id');
        $this->assertTrue(Hash::check('Secret123', User::query()->firstOrFail()->password));
        $this->assertSame('2026-08-11', User::query()->firstOrFail()->terms_version);
        $this->assertNotNull(User::query()->firstOrFail()->privacy_accepted_at);
        $this->assertSame('EG', User::query()->firstOrFail()->country_code);
        $this->assertNull(User::query()->firstOrFail()->phone_verified_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $verified = $this->postJson('/api/v1/auth/otp/verify', [
            'verificationToken' => $register->json('data.verificationToken'),
            'code' => '123456',
            'deviceName' => 'iPhone',
        ])->assertOk()->assertJsonPath('data.user.phoneVerified', true);
        $this->assertNotNull($verified->json('data.accessToken'));

        $this->postJson('/api/v1/auth/register', ['name' => 'No consent', 'email' => 'no-consent@example.com', 'password' => 'Secret123', 'password_confirmation' => 'Secret123'])
            ->assertUnprocessable()
            ->assertJsonStructure(['error' => ['details' => ['termsAccepted', 'privacyAccepted', 'legalVersion']]]);

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'DRIVER@example.com', 'password' => 'Secret123']);
        $token = $login->assertOk()->json('data.accessToken');
        $this->assertNotNull(User::query()->where('email', 'driver@example.com')->value('last_login_at'));
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.name', 'Driver');
        $this->withToken($token)->patchJson('/api/v1/settings', ['locale' => 'ar', 'themeMode' => 'dark', 'units' => 'imperial'])->assertOk()->assertJsonPath('data.themeMode', 'dark');
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_registration_without_a_phone_creates_a_session_immediately(): void
    {
        $response = $this->postJson('/api/v1/auth/register', [
            'name' => 'Private Driver',
            'email' => 'private-driver@example.com',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'deviceName' => 'iPad',
            'locale' => 'en',
            'termsAccepted' => true,
            'privacyAccepted' => true,
            'legalVersion' => '2026-08-11',
        ])->assertCreated()
            ->assertJsonPath('data.user.phone', null)
            ->assertJsonPath('data.tokenType', 'Bearer')
            ->assertJsonStructure(['data' => ['user', 'accessToken'], 'meta' => ['requestId']]);

        $this->assertNotEmpty($response->json('data.accessToken'));
        $user = User::query()->where('email', 'private-driver@example.com')->sole();
        $this->assertNull($user->phone);
        $this->assertNotNull($user->last_login_at);
        $this->assertSame('iPad', $user->tokens()->sole()->name);
    }

    public function test_validation_and_unauthenticated_errors_use_localized_envelope(): void
    {
        $this->withHeader('Accept-Language', 'ar')->postJson('/api/v1/auth/register', [])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED')->assertJsonPath('error.message', 'البيانات المُرسلة غير صالحة.')->assertJsonPath('error.details.name.0', 'حقل الاسم مطلوب.')->assertJsonStructure(['error' => ['details', 'requestId']]);
        $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
    }

    public function test_registration_and_login_default_device_name_when_client_sends_null(): void
    {
        $this->postJson('/api/v1/auth/register', [
            'name' => 'Driver',
            'email' => 'driver@example.com',
            'phone' => '+201009876543',
            'countryCode' => 'EG',
            'password' => 'Secret123',
            'password_confirmation' => 'Secret123',
            'deviceName' => null,
            'termsAccepted' => true,
            'privacyAccepted' => true,
            'legalVersion' => '2026-08-11',
        ])->assertCreated();

        $registration = $this->postJson('/api/v1/auth/login', [
            'email' => 'driver@example.com',
            'password' => 'Secret123',
        ])->assertForbidden()->assertJsonPath('error.code', 'OTP_REQUIRED');

        $this->postJson('/api/v1/auth/otp/verify', [
            'verificationToken' => $registration->json('error.details.verificationToken.0'),
            'code' => '123456',
            'deviceName' => null,
        ])->assertOk();

        $user = User::query()->where('email', 'driver@example.com')->firstOrFail();
        $this->assertSame('AutoMind mobile', $user->tokens()->sole()->name);
        $user->tokens()->delete();

        $this->postJson('/api/v1/auth/login', [
            'email' => 'driver@example.com',
            'password' => 'Secret123',
            'deviceName' => null,
        ])->assertOk();

        $this->assertSame('AutoMind mobile', $user->tokens()->sole()->name);
    }

    public function test_unverified_login_routes_to_otp_and_rejects_an_invalid_code(): void
    {
        $user = User::factory()->create([
            'email' => 'pending@example.com',
            'phone' => '+201112223333',
            'phone_verified_at' => null,
        ]);

        $login = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertForbidden()
            ->assertJsonPath('error.code', 'OTP_REQUIRED')
            ->assertJsonPath('error.details.purpose.0', 'login')
            ->assertJsonStructure(['error' => ['details' => ['verificationToken', 'maskedPhone']]]);

        $this->postJson('/api/v1/auth/otp/verify', [
            'verificationToken' => $login->json('error.details.verificationToken.0'),
            'code' => '000000',
        ])->assertUnprocessable()->assertJsonPath('error.code', 'OTP_INVALID');

        $this->assertNull($user->fresh()->phone_verified_at);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_login_rate_limit_uses_stable_localized_error(): void
    {
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $this->postJson('/api/v1/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong-password']);
        }

        $this->withHeader('Accept-Language', 'ar')->postJson('/api/v1/auth/login', ['email' => 'missing@example.com', 'password' => 'wrong-password'])
            ->assertStatus(429)
            ->assertJsonPath('error.code', 'RATE_LIMITED')
            ->assertJsonPath('error.message', 'طلبات كثيرة جداً. يرجى المحاولة لاحقاً.');
    }

    public function test_profile_update_does_not_mass_assign_admin_or_status_fields(): void
    {
        $user = $this->actingAsUser();
        $this->patchJson('/api/v1/me', ['name' => 'Updated', 'isAdmin' => true, 'status' => 'admin'])->assertOk()->assertJsonPath('data.name', 'Updated');
        $this->assertFalse($user->fresh()->is_admin);
    }

    public function test_account_deletion_revokes_devices_and_cancels_active_analysis(): void
    {
        $user = $this->actingAsUser();
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id, 'status' => 'analyzing']);
        $device = DeviceToken::query()->create(['user_id' => $user->id, 'platform' => 'ios', 'push_token' => 'push-token', 'token_hash' => hash('sha256', 'push-token'), 'enabled' => true]);

        $this->deleteJson('/api/v1/me')->assertNoContent();

        $this->assertSame('cancelled', $session->fresh()->status);
        $this->assertFalse((bool) $device->fresh()->enabled);
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_device_registration_is_idempotent_and_refreshes_metadata(): void
    {
        $user = $this->actingAsUser();

        $this->postJson('/api/v1/devices', [
            'platform' => 'android',
            'token' => 'fcm-registration-token',
            'deviceName' => 'Pixel',
            'appVersion' => '1.0.0+1',
        ])->assertCreated()->assertJsonPath('data.platform', 'android');

        $device = DeviceToken::query()->sole();
        $this->assertSame($user->id, $device->user_id);
        $this->assertSame('fcm-registration-token', $device->push_token);
        $this->assertNotNull($device->last_seen_at);

        $this->postJson('/api/v1/devices', [
            'platform' => 'android',
            'token' => 'fcm-registration-token',
            'deviceName' => 'Pixel 2',
            'appVersion' => '1.0.1+2',
        ])->assertOk()->assertJsonPath('data.id', (string) $device->id);

        $this->assertDatabaseCount('device_tokens', 1);
        $this->assertSame('Pixel 2', $device->fresh()->device_name);
    }

    public function test_logout_all_revokes_sessions_and_push_devices(): void
    {
        $user = $this->actingAsUser();
        $secondToken = $user->createToken('second-device');
        $device = DeviceToken::query()->create([
            'user_id' => $user->id,
            'platform' => 'android',
            'push_token' => 'logout-all-push-token',
            'token_hash' => hash('sha256', 'logout-all-push-token'),
            'enabled' => true,
        ]);

        $this->postJson('/api/v1/auth/logout-all')->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $secondToken->accessToken->id,
        ]);
        $this->assertFalse((bool) $device->fresh()->enabled);
    }

    public function test_social_login_requires_email_only_when_linking_a_new_identity(): void
    {
        $existingUser = User::factory()->create();
        SocialIdentity::query()->create(['user_id' => $existingUser->id, 'provider' => 'apple', 'provider_subject' => 'known-subject']);
        $verifier = Mockery::mock(SocialIdentityVerifier::class);
        $verifier->shouldReceive('verify')->twice()->andReturnUsing(fn (string $provider, string $token) => [
            'subject' => $token === 'known-token' ? 'known-subject' : 'new-subject', 'email' => null, 'name' => null,
        ]);
        $this->app->instance(SocialIdentityVerifier::class, $verifier);

        $this->postJson('/api/v1/auth/social/apple', ['identityToken' => 'new-token', 'nonce' => 'raw-nonce', 'termsAccepted' => true, 'privacyAccepted' => true, 'legalVersion' => '2026-08-11'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'SOCIAL_EMAIL_REQUIRED');
        $this->postJson('/api/v1/auth/social/apple', ['identityToken' => 'known-token', 'nonce' => 'raw-nonce'])
            ->assertOk()->assertJsonPath('data.user.id', $existingUser->id)->assertJsonStructure(['data' => ['accessToken']]);
    }

    public function test_apple_social_login_accepts_first_authorization_name_and_marks_login_time(): void
    {
        $verifier = Mockery::mock(SocialIdentityVerifier::class);
        $verifier->shouldReceive('verify')->once()->andReturn([
            'subject' => 'new-apple-subject',
            'email' => 'private-relay@example.com',
            'name' => null,
        ]);
        $this->app->instance(SocialIdentityVerifier::class, $verifier);

        $this->postJson('/api/v1/auth/social/apple', [
            'identityToken' => 'apple-token',
            'nonce' => 'raw-nonce',
            'name' => 'Apple Driver',
            'deviceName' => 'iPhone',
            'termsAccepted' => true,
            'privacyAccepted' => true,
            'legalVersion' => '2026-08-11',
        ])->assertOk()
            ->assertJsonPath('data.user.name', 'Apple Driver')
            ->assertJsonPath('data.user.email', 'private-relay@example.com');

        $user = User::query()->where('email', 'private-relay@example.com')->sole();
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull($user->last_login_at);
        $this->assertSame('2026-08-11', $user->terms_version);
        $this->assertDatabaseHas('social_identities', [
            'user_id' => $user->id,
            'provider' => 'apple',
            'provider_subject' => 'new-apple-subject',
        ]);
    }

    public function test_apple_social_login_requires_nonce(): void
    {
        $this->postJson('/api/v1/auth/social/apple', ['identityToken' => 'apple-token'])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonStructure(['error' => ['details' => ['nonce']]]);
    }
}
