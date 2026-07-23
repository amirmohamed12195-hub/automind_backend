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
            'app.debug' => false,
            'app.url' => 'https://automind.example',
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
}
