<?php

namespace Tests\Unit;

use App\Services\Ai\OpenAiConfigurationValidator;
use App\Services\ProductionConfigurationValidator;
use Tests\TestCase;

class ProductionConfigurationValidatorTest extends TestCase
{
    public function test_valid_mysql_production_configuration_passes(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'app.cipher' => 'AES-256-CBC',
            'app.env' => 'production',
            'app.debug' => false,
            'app.url' => 'https://automind.example',
            'public.support_email' => 'support@automind.example',
            'public.privacy_email' => 'privacy@automind.example',
            'public.app_links.apple_team_id' => 'ABCDE12345',
            'public.app_links.android_sha256_fingerprints' => [str_repeat('AA:', 31).'AA'],
            'admin.username' => 'admin',
            'admin.password_hash' => password_hash('strong-password', PASSWORD_BCRYPT),
            'automind.social_login_enabled' => true,
            'services.google.client_ids' => ['google-client-id'],
            'services.apple.client_ids' => ['apple-client-id'],
            'services.twilio.whatsapp.enabled' => true,
            'services.twilio.whatsapp.from' => '+14155238886',
            'services.twilio.whatsapp.content_sid' => 'HX'.str_repeat('a', 32),
            'services.twilio.api_key' => 'SK'.str_repeat('b', 32),
            'services.twilio.api_secret' => 'production-api-secret',
            'services.twilio.account_sid' => 'AC'.str_repeat('c', 32),
            'services.twilio.auth_token' => null,
            'automind.push_notifications_enabled' => true,
            'services.fcm.project_id' => 'automind-production',
            'services.fcm.credentials_path' => '/run/secrets/firebase.json',
            'services.fcm.credentials_base64' => null,
            'queue.default' => 'database',
            'mail.default' => 'smtp',
            'mail.mailers.smtp.host' => 'smtp.automind.example',
            'mail.mailers.smtp.username' => 'mailer',
            'mail.mailers.smtp.password' => 'secret',
            'mail.from.address' => 'noreply@automind.example',
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'host' => '127.0.0.1',
                'database' => 'automind',
                'username' => 'automind',
                'password' => 'secret',
            ],
        ]);

        $this->assertSame([], app(ProductionConfigurationValidator::class)->errors());
    }

    public function test_missing_app_key_and_mysql_placeholders_fail_preflight(): void
    {
        config([
            'app.key' => '',
            'app.debug' => false,
            'app.url' => 'https://automind.example',
            'admin.username' => 'admin',
            'admin.password_hash' => password_hash('strong-password', PASSWORD_BCRYPT),
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'host' => '127.0.0.1',
                'database' => 'automind',
                'username' => 'automind',
                'password' => 'REPLACE_WITH_A_NEW_DATABASE_PASSWORD',
            ],
        ]);

        $errors = app(ProductionConfigurationValidator::class)->errors();

        $this->assertContains('APP_KEY is required. Run php artisan key:generate --force for the first deployment.', $errors);
        $this->assertContains('DB_PASSWORD must contain the production MySQL value.', $errors);
    }

    public function test_concatenated_openai_environment_line_is_rejected(): void
    {
        config(['openai.api_key' => 'sk-testOPENAI_BASE_URL=https://api.openai.com/v1']);

        $this->assertContains(
            'OPENAI_API_KEY is malformed or still contains a placeholder.',
            app(OpenAiConfigurationValidator::class)->errors(),
        );
    }

    public function test_invalid_reasoning_effort_is_rejected(): void
    {
        config(['openai.diagnosis_reasoning_effort' => 'fastest']);

        $this->assertContains(
            'OPENAI_DIAGNOSIS_REASONING_EFFORT must be none, low, medium, high, xhigh, or max.',
            app(OpenAiConfigurationValidator::class)->errors(false),
        );
    }

    public function test_enabled_social_login_requires_both_provider_client_ids(): void
    {
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
            'app.cipher' => 'AES-256-CBC',
            'app.debug' => false,
            'app.url' => 'https://automind.example',
            'admin.username' => 'admin',
            'admin.password_hash' => password_hash('strong-password', PASSWORD_BCRYPT),
            'automind.social_login_enabled' => true,
            'services.google.client_ids' => [],
            'services.apple.client_ids' => ['CHANGE_ME'],
            'database.default' => 'mysql',
            'database.connections.mysql' => [
                'host' => '127.0.0.1',
                'database' => 'automind',
                'username' => 'automind',
                'password' => 'secret',
            ],
        ]);

        $errors = app(ProductionConfigurationValidator::class)->errors();

        $this->assertContains('GOOGLE_CLIENT_IDS must contain at least one production OAuth client ID when SOCIAL_LOGIN_ENABLED=true.', $errors);
        $this->assertContains('APPLE_CLIENT_IDS must contain at least one production OAuth client ID when SOCIAL_LOGIN_ENABLED=true.', $errors);
    }
}
