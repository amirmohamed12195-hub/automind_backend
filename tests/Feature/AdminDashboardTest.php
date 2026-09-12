<?php

namespace Tests\Feature;

use App\Models\DeviceToken;
use App\Models\DiagnosticMedia;
use App\Models\DiagnosticSession;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_renders_live_application_data(): void
    {
        $user = User::factory()->create(['name' => 'Dashboard Driver']);
        Vehicle::factory()->for($user)->create(['brand' => 'Toyota', 'model' => 'Corolla']);

        $this->asWebAdmin()->get('/admin')
            ->assertOk()
            ->assertSee('Command center')
            ->assertSee('Dashboard Driver')
            ->assertSee('Toyota Corolla')
            ->assertSee('PLATFORM CONTROL');
    }

    public function test_admin_can_suspend_and_reactivate_a_user(): void
    {
        $user = User::factory()->create();
        $user->createToken('mobile');

        $this->asWebAdmin()->post(route('admin.users.suspension', $user), [
            'suspended' => true,
            'reason' => 'Chargeback review',
        ])->assertRedirect(route('admin.dashboard').'#users');

        $this->assertNotNull($user->fresh()->suspended_at);
        $this->assertSame('Chargeback review', $user->fresh()->suspension_reason);
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->asWebAdmin()->post(route('admin.users.suspension', $user), [
            'suspended' => false,
        ])->assertRedirect(route('admin.dashboard').'#users');

        $this->assertNull($user->fresh()->suspended_at);
    }

    public function test_admin_can_permanently_delete_a_user_and_related_data(): void
    {
        Storage::fake('local');
        config(['automind.media.disk' => 'local']);
        $user = User::factory()->create(['email' => 'delete-me@example.com', 'avatar_path' => 'avatars/delete-me/avatar.jpg']);
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id]);
        $media = DiagnosticMedia::query()->create([
            'diagnostic_session_id' => $session->id, 'media_kind' => 'photo', 'storage_disk' => 'local',
            'storage_path' => 'diagnostics/delete-me/photo.jpg', 'original_filename' => 'photo.jpg', 'mime_type' => 'image/jpeg',
            'extension' => 'jpg', 'byte_size' => 100, 'sha256' => hash('sha256', 'delete-me-photo'),
            'upload_status' => 'uploaded', 'scan_status' => 'clean', 'processing_status' => 'ready',
        ]);
        $user->createToken('mobile');
        DeviceToken::query()->create([
            'user_id' => $user->id, 'platform' => 'ios', 'push_token' => 'delete-me-token',
            'token_hash' => hash('sha256', 'delete-me-token'), 'enabled' => true,
        ]);
        DB::table('sessions')->insert([
            'id' => 'delete-me-session', 'user_id' => $user->id, 'payload' => 'payload', 'last_activity' => time(),
        ]);
        DB::table('password_reset_tokens')->insert([
            'email' => $user->email, 'token' => 'reset-token', 'created_at' => now(),
        ]);
        Storage::disk('local')->put($user->avatar_path, 'avatar');
        Storage::disk('local')->put($media->storage_path, 'photo');

        $this->asWebAdmin()->delete(route('admin.users.destroy', $user->id), [
            'confirmation' => $user->email,
        ])->assertRedirect(route('admin.dashboard').'#users');

        $this->assertNull(User::withTrashed()->find($user->id));
        $this->assertDatabaseMissing('vehicles', ['id' => $vehicle->id]);
        $this->assertDatabaseMissing('diagnostic_sessions', ['id' => $session->id]);
        $this->assertDatabaseMissing('diagnostic_media', ['id' => $media->id]);
        $this->assertDatabaseMissing('device_tokens', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => $user->email]);
        Storage::disk('local')->assertMissing($user->avatar_path);
        Storage::disk('local')->assertMissing($media->storage_path);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'admin.web.user.permanently_deleted', 'target_id' => $user->id,
        ]);
    }

    public function test_permanent_user_deletion_requires_the_exact_email(): void
    {
        $user = User::factory()->create(['email' => 'keep-me@example.com']);

        $this->asWebAdmin()->from(route('admin.dashboard').'#users')->delete(route('admin.users.destroy', $user->id), [
            'confirmation' => 'wrong@example.com',
        ])->assertRedirect(route('admin.dashboard').'#users')->assertSessionHasErrors('confirmation');

        $this->assertNotNull(User::withTrashed()->find($user->id));
    }

    public function test_admin_can_permanently_delete_a_previously_soft_deleted_user(): void
    {
        $user = User::factory()->create(['email' => 'already-deleted@example.com']);
        $user->delete();

        $this->asWebAdmin()->delete(route('admin.users.destroy', $user->id), [
            'confirmation' => $user->email,
        ])->assertRedirect(route('admin.dashboard').'#users');

        $this->assertNull(User::withTrashed()->find($user->id));
    }

    public function test_suspended_user_cannot_log_in_or_use_an_existing_token(): void
    {
        $user = User::factory()->create(['email' => 'blocked@example.com', 'suspended_at' => now()]);
        $token = $user->createToken('mobile')->plainTextToken;

        $this->postJson('/api/v1/auth/login', ['email' => 'blocked@example.com', 'password' => 'password'])
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');

        $this->withToken($token)->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'ACCOUNT_SUSPENDED');
    }

    public function test_feature_settings_are_persisted_and_enforced(): void
    {
        $this->asWebAdmin()->patch(route('admin.settings.update'), [
            'settings' => [
                'registration_enabled' => false,
                'diagnostics_enabled' => true,
                'appointments_enabled' => true,
                'maintenance_banner' => 'Scheduled maintenance tonight',
                'support_email' => 'care@automind.example',
                'default_country' => 'eg',
                'default_currency' => 'egp',
                'default_locale' => 'ar',
            ],
        ])->assertRedirect(route('admin.dashboard').'#settings');

        $this->assertFalse(PlatformSetting::query()->findOrFail('registration_enabled')->value);
        $this->assertSame('EGP', PlatformSetting::query()->findOrFail('default_currency')->value);
        $this->get('/support')->assertOk()->assertSee('care@automind.example');

        $this->postJson('/api/v1/auth/register', [])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'FEATURE_UNAVAILABLE');
    }

    public function test_dashboard_uses_compatibility_mode_when_admin_migration_is_pending(): void
    {
        User::factory()->create(['name' => 'Legacy Driver']);
        Schema::dropIfExists('platform_settings');
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_suspended_at_index');
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['last_login_at', 'suspended_at', 'suspension_reason']);
        });

        $this->asWebAdmin()->get('/admin')
            ->assertOk()
            ->assertSee('Database upgrade required')
            ->assertSee('Legacy Driver');
    }

    private function asWebAdmin(): static
    {
        return $this->withSession([
            config('admin.session_key') => true,
            'automind_admin_username' => 'admin',
        ]);
    }
}
