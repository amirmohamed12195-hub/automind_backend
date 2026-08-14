<?php

namespace App\Services\Billing;

use Illuminate\Support\Facades\Process;
use RuntimeException;

class BillingConfigurationValidator
{
    /** @return array<int, string> */
    public function errors(bool $requireEnabled = false): array
    {
        if (! $requireEnabled && ! (bool) config('billing.enabled')) {
            return [];
        }

        $errors = [];
        if (! in_array(config('billing.environment'), ['sandbox', 'production'], true)) {
            $errors[] = 'BILLING_ENVIRONMENT must be sandbox or production.';
        }
        foreach (['webhook_base_url' => 'BILLING_WEBHOOK_BASE_URL', 'terms_url' => 'BILLING_TERMS_URL', 'privacy_url' => 'BILLING_PRIVACY_URL'] as $key => $name) {
            if (! $this->httpsUrl(config("billing.$key"))) {
                $errors[] = "$name must contain a production HTTPS URL when billing is enabled.";
            }
        }
        $secret = trim((string) config('billing.account_obfuscation_secret'));
        if (strlen($secret) < 32 || $this->placeholder($secret)) {
            $errors[] = 'ACCOUNT_OBFUSCATION_SECRET must be a stable random secret of at least 32 characters.';
        }

        $bundleId = trim((string) config('billing.apple.bundle_id'));
        if ($bundleId === '' || $bundleId !== config('public.app_links.apple_bundle_id')) {
            $errors[] = 'APPLE_BUNDLE_ID must match the mobile application bundle identifier.';
        }
        if (! ctype_digit(trim((string) config('billing.apple.app_id')))) {
            $errors[] = 'APPLE_APP_ID must contain the numeric App Store application ID.';
        }
        foreach (['issuer_id' => 'APPLE_ISSUER_ID', 'key_id' => 'APPLE_KEY_ID'] as $key => $name) {
            $value = trim((string) config("billing.apple.$key"));
            if ($value === '' || $this->placeholder($value)) {
                $errors[] = "$name is required when billing is enabled.";
            }
        }
        if (! $this->applePrivateKeyAvailable()) {
            $errors[] = 'Configure a readable APPLE_PRIVATE_KEY_PATH or a valid APPLE_PRIVATE_KEY.';
        }
        if (! $this->appleRootsAvailable()) {
            $errors[] = 'APPLE_ROOT_CERTIFICATES_PATH must contain at least one readable Apple root certificate.';
        }
        if ((bool) config('billing.apple.online_certificate_checks') && ! $this->opensslAvailable()) {
            $errors[] = 'APPLE_OPENSSL_BINARY must point to an executable OpenSSL binary for online certificate checks.';
        }

        if (trim((string) config('billing.google.package_name')) !== config('public.app_links.android_package')) {
            $errors[] = 'GOOGLE_PLAY_PACKAGE_NAME must match the Android application ID.';
        }
        $projectId = trim((string) config('billing.google.project_id'));
        if ($projectId === '' || $this->placeholder($projectId)) {
            $errors[] = 'GOOGLE_PLAY_PROJECT_ID is required when billing is enabled.';
        }
        if (! $this->googleCredentialsAvailable()) {
            $errors[] = 'Configure valid Google Play service-account JSON with GOOGLE_PLAY_SERVICE_ACCOUNT_PATH or GOOGLE_PLAY_SERVICE_ACCOUNT.';
        }
        if (! $this->httpsUrl(config('billing.google.pubsub_audience'))) {
            $errors[] = 'GOOGLE_PLAY_PUBSUB_AUDIENCE must be the HTTPS Google webhook URL.';
        }
        if (! filter_var(config('billing.google.pubsub_service_account_email'), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'GOOGLE_PLAY_PUBSUB_SERVICE_ACCOUNT_EMAIL must contain the authenticated Pub/Sub push identity.';
        }
        $topic = trim((string) config('billing.google.pubsub_topic'));
        if ($topic === '' || $this->placeholder($topic)) {
            $errors[] = 'GOOGLE_PLAY_PUBSUB_TOPIC is required when billing is enabled.';
        }

        return $errors;
    }

    public function validate(bool $requireEnabled = false): void
    {
        $errors = $this->errors($requireEnabled);
        if ($errors !== []) {
            throw new RuntimeException('Billing configuration is invalid: '.implode(' ', $errors));
        }
    }

    private function applePrivateKeyAvailable(): bool
    {
        $inline = str_replace('\\n', "\n", trim((string) config('billing.apple.private_key')));
        if (str_contains($inline, 'BEGIN PRIVATE KEY')) {
            return true;
        }
        $path = trim((string) config('billing.apple.private_key_path'));

        return $path !== '' && is_readable($path) && str_contains((string) file_get_contents($path), 'BEGIN PRIVATE KEY');
    }

    private function appleRootsAvailable(): bool
    {
        $location = trim((string) config('billing.apple.root_certificates_path'));
        if (is_readable($location) && is_file($location)) {
            return true;
        }

        return is_dir($location) && collect(glob(rtrim($location, '/').'/*.{cer,crt,pem}', GLOB_BRACE) ?: [])->contains(fn (string $path): bool => is_readable($path));
    }

    private function googleCredentialsAvailable(): bool
    {
        $json = trim((string) config('billing.google.service_account'));
        $path = trim((string) config('billing.google.service_account_path'));
        if ($json === '' && $path !== '' && is_readable($path)) {
            $json = (string) file_get_contents($path);
        }
        $credentials = json_decode($json, true);

        return is_array($credentials)
            && ($credentials['type'] ?? null) === 'service_account'
            && is_string($credentials['client_email'] ?? null)
            && filter_var($credentials['client_email'], FILTER_VALIDATE_EMAIL)
            && is_string($credentials['private_key'] ?? null)
            && str_contains($credentials['private_key'], 'BEGIN PRIVATE KEY');
    }

    private function opensslAvailable(): bool
    {
        $binary = trim((string) config('billing.apple.openssl_binary', 'openssl'));
        if ($binary === '') {
            return false;
        }

        try {
            return Process::timeout(3)->run([$binary, 'version'])->successful();
        } catch (\Throwable) {
            return false;
        }
    }

    private function httpsUrl(mixed $value): bool
    {
        return is_string($value)
            && filter_var($value, FILTER_VALIDATE_URL)
            && parse_url($value, PHP_URL_SCHEME) === 'https'
            && ! $this->placeholder($value);
    }

    private function placeholder(string $value): bool
    {
        $value = strtoupper($value);

        return str_contains($value, 'CHANGE_ME')
            || str_contains($value, 'REPLACE_WITH')
            || str_contains($value, 'EXAMPLE.COM');
    }
}
