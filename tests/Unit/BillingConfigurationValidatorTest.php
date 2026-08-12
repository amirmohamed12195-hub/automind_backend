<?php

namespace Tests\Unit;

use App\Services\Billing\BillingConfigurationValidator;
use Tests\TestCase;

class BillingConfigurationValidatorTest extends TestCase
{
    public function test_disabled_billing_does_not_require_store_credentials(): void
    {
        config(['billing.enabled' => false]);

        $this->assertSame([], app(BillingConfigurationValidator::class)->errors());
    }

    public function test_enabled_billing_fails_closed_when_store_credentials_are_missing(): void
    {
        config([
            'billing.enabled' => true,
            'billing.environment' => 'sandbox',
            'billing.webhook_base_url' => 'https://automind-ai.net/api/v1/webhooks',
            'billing.terms_url' => 'https://automind-ai.net/terms',
            'billing.privacy_url' => 'https://automind-ai.net/privacy',
            'billing.account_obfuscation_secret' => '',
            'billing.apple.app_id' => null,
            'billing.apple.issuer_id' => null,
            'billing.apple.key_id' => null,
            'billing.apple.private_key' => null,
            'billing.apple.private_key_path' => null,
            'billing.apple.root_certificates_path' => null,
            'billing.google.project_id' => null,
            'billing.google.service_account' => null,
            'billing.google.service_account_path' => null,
            'billing.google.pubsub_audience' => null,
            'billing.google.pubsub_service_account_email' => null,
            'billing.google.pubsub_topic' => null,
        ]);

        $errors = app(BillingConfigurationValidator::class)->errors();

        $this->assertContains('ACCOUNT_OBFUSCATION_SECRET must be a stable random secret of at least 32 characters.', $errors);
        $this->assertContains('APPLE_APP_ID must contain the numeric App Store application ID.', $errors);
        $this->assertContains('GOOGLE_PLAY_PROJECT_ID is required when billing is enabled.', $errors);
    }
}
