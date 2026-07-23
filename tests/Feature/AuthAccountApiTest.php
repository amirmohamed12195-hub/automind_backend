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
        $register = $this->postJson('/api/v1/auth/register', ['name' => 'Driver', 'email' => ' DRIVER@EXAMPLE.COM ', 'password' => 'Secret123', 'password_confirmation' => 'Secret123', 'deviceName' => 'iPhone', 'locale' => 'en']);
        $register->assertCreated()->assertJsonPath('data.user.email', 'driver@example.com')->assertJsonStructure(['data' => ['user' => ['id', 'name', 'email'], 'accessToken', 'tokenType'], 'meta' => ['requestId']])->assertHeader('X-Request-Id');
        $this->assertTrue(Hash::check('Secret123', User::query()->firstOrFail()->password));

        $login = $this->postJson('/api/v1/auth/login', ['email' => 'DRIVER@example.com', 'password' => 'Secret123']);
        $token = $login->assertOk()->json('data.accessToken');
        $this->withToken($token)->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.name', 'Driver');
        $this->withToken($token)->patchJson('/api/v1/settings', ['locale' => 'ar', 'themeMode' => 'dark', 'units' => 'imperial'])->assertOk()->assertJsonPath('data.themeMode', 'dark');
        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_validation_and_unauthenticated_errors_use_localized_envelope(): void
    {
        $this->withHeader('Accept-Language', 'ar')->postJson('/api/v1/auth/register', [])->assertUnprocessable()->assertJsonPath('error.code', 'VALIDATION_FAILED')->assertJsonPath('error.message', 'البيانات المُرسلة غير صالحة.')->assertJsonPath('error.details.name.0', 'حقل الاسم مطلوب.')->assertJsonStructure(['error' => ['details', 'requestId']]);
        $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonPath('error.code', 'UNAUTHENTICATED');
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

    public function test_social_login_requires_email_only_when_linking_a_new_identity(): void
    {
        $existingUser = User::factory()->create();
        SocialIdentity::query()->create(['user_id' => $existingUser->id, 'provider' => 'apple', 'provider_subject' => 'known-subject']);
        $verifier = Mockery::mock(SocialIdentityVerifier::class);
        $verifier->shouldReceive('verify')->twice()->andReturnUsing(fn (string $provider, string $token) => [
            'subject' => $token === 'known-token' ? 'known-subject' : 'new-subject', 'email' => null, 'name' => null,
        ]);
        $this->app->instance(SocialIdentityVerifier::class, $verifier);

        $this->postJson('/api/v1/auth/social/apple', ['identityToken' => 'new-token'])
            ->assertUnprocessable()->assertJsonPath('error.code', 'SOCIAL_EMAIL_REQUIRED');
        $this->postJson('/api/v1/auth/social/apple', ['identityToken' => 'known-token'])
            ->assertOk()->assertJsonPath('data.user.id', $existingUser->id)->assertJsonStructure(['data' => ['accessToken']]);
    }
}
