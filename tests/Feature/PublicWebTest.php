<?php

namespace Tests\Feature;

use App\Models\DiagnosticSession;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\ConfirmAccountDeletion;
use App\Services\Diagnostics\DiagnosticReportPersister;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\Fakes\FakeAiProviders;

class PublicWebTest extends ApiTestCase
{
    public function test_public_legal_support_and_account_deletion_pages_are_available_in_both_languages(): void
    {
        $this->get('/privacy')->assertOk()->assertSee('Privacy Policy');
        $this->get('/privacy?lang=ar')->assertOk()->assertSee('سياسة الخصوصية')->assertSee('dir="rtl"', false);
        $this->get('/terms')->assertOk()->assertSee('Terms of Use');
        $this->get('/support?lang=ar')->assertOk()->assertSee('الدعم والمساعدة');
        $this->get('/delete-account')->assertOk()->assertSee('Delete your AutoMind account');
    }

    public function test_web_deletion_requires_email_confirmation_and_uses_the_same_deletion_service_as_the_api(): void
    {
        Notification::fake();
        $user = User::factory()->create(['email' => 'driver@example.com', 'locale' => 'en']);

        $this->post('/delete-account', ['email' => 'driver@example.com', 'lang' => 'en'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $confirmationUrl = null;
        Notification::assertSentTo($user, ConfirmAccountDeletion::class, function (ConfirmAccountDeletion $notification) use ($user, &$confirmationUrl): bool {
            $confirmationUrl = $notification->toMail($user)->actionUrl;

            return is_string($confirmationUrl) && str_contains($confirmationUrl, '/delete-account/confirm/');
        });

        $this->get($confirmationUrl)->assertOk()->assertSee('Confirm account deletion');
        $this->post($confirmationUrl)->assertRedirect('/delete-account?lang=en');
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_web_password_reset_changes_password_and_revokes_tokens(): void
    {
        $user = User::factory()->create(['email' => 'driver@example.com']);
        $user->createToken('phone');
        $token = Password::broker()->createToken($user);

        $this->get('/reset-password?'.http_build_query(['email' => $user->email, 'token' => $token]))
            ->assertOk()
            ->assertSee('Reset password');
        $this->post('/reset-password', [
            'email' => $user->email,
            'token' => $token,
            'password' => 'NewSecret123',
            'password_confirmation' => 'NewSecret123',
            'lang' => 'en',
        ])->assertRedirect('/reset-password?lang=en');

        $this->assertTrue(Hash::check('NewSecret123', $user->fresh()->password));
        $this->assertDatabaseMissing('personal_access_tokens', ['tokenable_id' => $user->id]);
    }

    public function test_association_files_are_generated_from_release_identity_configuration(): void
    {
        config([
            'public.app_links.android_package' => 'com.automind.ai',
            'public.app_links.android_sha256_fingerprints' => [str_repeat('AA:', 31).'AA'],
            'public.app_links.apple_team_id' => 'ABCDE12345',
            'public.app_links.apple_bundle_id' => 'com.automind.ai',
        ]);

        $this->get('/.well-known/assetlinks.json')
            ->assertOk()
            ->assertJsonPath('0.target.package_name', 'com.automind.ai');
        $this->get('/.well-known/apple-app-site-association')
            ->assertOk()
            ->assertJsonPath('applinks.details.0.appID', 'ABCDE12345.com.automind.ai');
    }

    public function test_shared_report_renders_html_for_browsers_and_json_for_api_clients(): void
    {
        $user = User::factory()->create();
        $vehicle = Vehicle::factory()->for($user)->create();
        $session = DiagnosticSession::factory()->create(['user_id' => $user->id, 'vehicle_id' => $vehicle->id]);
        $report = app(DiagnosticReportPersister::class)->persist($session, FakeAiProviders::report());
        $url = URL::temporarySignedRoute('reports.shared', now()->addMinutes(5), [
            'report' => $report->id,
            'locale' => 'en',
        ]);

        $this->get($url, ['Accept' => 'text/html'])->assertOk()->assertSee('Shared diagnostic report');
        $this->getJson($url)->assertOk()->assertJsonPath('data.id', $report->id);
    }
}
