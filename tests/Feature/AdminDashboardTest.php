<?php

namespace Tests\Feature;

use App\Models\PlatformSetting;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
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
