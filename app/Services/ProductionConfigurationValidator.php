<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;

class ProductionConfigurationValidator
{
    public function errors(): array
    {
        $errors = [];
        $appKey = (string) config('app.key');
        $decodedAppKey = $this->decodeAppKey($appKey);

        if (config('app.env') !== 'production') {
            $errors[] = 'APP_ENV must be production for a production deployment.';
        }

        if ($appKey === '') {
            $errors[] = 'APP_KEY is required. Run php artisan key:generate --force for the first deployment.';
        } elseif ($decodedAppKey === null || ! Encrypter::supported($decodedAppKey, (string) config('app.cipher'))) {
            $errors[] = 'APP_KEY is invalid for the configured application cipher.';
        }

        if ((bool) config('app.debug')) {
            $errors[] = 'APP_DEBUG must be false in production.';
        }

        $adminUsername = (string) config('admin.username');
        $adminPasswordHash = (string) config('admin.password_hash');
        if ($adminUsername === '' || $this->isPlaceholder($adminUsername)) {
            $errors[] = 'ADMIN_WEB_USERNAME must contain the production administrator username.';
        }
        if (
            $adminPasswordHash === ''
            || $this->isPlaceholder($adminPasswordHash)
            || password_get_info($adminPasswordHash)['algo'] === null
        ) {
            $errors[] = 'ADMIN_WEB_PASSWORD_HASH must contain a valid password hash. Run php artisan automind:configure-admin.';
        }

        $appUrl = (string) config('app.url');
        if (! str_starts_with($appUrl, 'https://')) {
            $errors[] = 'APP_URL must use HTTPS in production.';
        }
        if (! filter_var(config('public.support_email'), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'SUPPORT_EMAIL must contain a valid public support address.';
        }
        if (! filter_var(config('public.privacy_email'), FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'PRIVACY_EMAIL must contain a valid privacy contact address.';
        }
        $teamId = trim((string) config('public.app_links.apple_team_id'));
        if ($teamId === '' || $this->isPlaceholder($teamId)) {
            $errors[] = 'APPLE_TEAM_ID is required for iOS Universal Links.';
        }
        $fingerprints = config('public.app_links.android_sha256_fingerprints');
        if (! is_array($fingerprints) || $fingerprints === [] || collect($fingerprints)->contains(fn ($value) => ! is_string($value) || ! preg_match('/^(?:[0-9A-F]{2}:){31}[0-9A-F]{2}$/i', $value))) {
            $errors[] = 'ANDROID_APP_LINK_SHA256_CERT_FINGERPRINTS must contain valid Play signing SHA-256 fingerprints.';
        }

        if ((bool) config('automind.social_login_enabled')) {
            foreach (['google' => 'GOOGLE_CLIENT_IDS', 'apple' => 'APPLE_CLIENT_IDS'] as $provider => $environmentName) {
                $clientIds = config("services.$provider.client_ids");
                if (! is_array($clientIds) || $clientIds === [] || collect($clientIds)->contains(fn ($id) => ! is_string($id) || trim($id) === '' || $this->isPlaceholder($id))) {
                    $errors[] = "$environmentName must contain at least one production OAuth client ID when SOCIAL_LOGIN_ENABLED=true.";
                }
            }
        }

        if (in_array(config('queue.default'), ['sync', 'null'], true)) {
            $errors[] = 'QUEUE_CONNECTION must use a durable asynchronous driver in production.';
        }

        if (in_array(config('mail.default'), ['array', 'log'], true)) {
            $errors[] = 'MAIL_MAILER must deliver email in production so password resets work.';
        }
        if (config('mail.default') === 'smtp') {
            $smtp = config('mail.mailers.smtp');
            foreach (['host' => 'MAIL_HOST', 'username' => 'MAIL_USERNAME', 'password' => 'MAIL_PASSWORD'] as $key => $environmentName) {
                $value = is_array($smtp) ? $smtp[$key] ?? null : null;
                if (! is_string($value) || trim($value) === '' || $this->isPlaceholder($value)) {
                    $errors[] = "$environmentName must contain the production SMTP value.";
                }
            }
        }
        $fromAddress = (string) config('mail.from.address');
        if (! filter_var($fromAddress, FILTER_VALIDATE_EMAIL) || str_ends_with(mb_strtolower($fromAddress), '@example.com')) {
            $errors[] = 'MAIL_FROM_ADDRESS must contain a deliverable production address.';
        }

        if ((bool) config('automind.push_notifications_enabled')) {
            $fcmProjectId = (string) config('services.fcm.project_id');
            $fcmCredentialsPath = (string) config('services.fcm.credentials_path');
            $fcmCredentialsBase64 = (string) config('services.fcm.credentials_base64');
            if ($fcmProjectId === '' || $this->isPlaceholder($fcmProjectId)) {
                $errors[] = 'FCM_PROJECT_ID is required when PUSH_NOTIFICATIONS_ENABLED=true.';
            }
            if (
                ($fcmCredentialsPath === '' || $this->isPlaceholder($fcmCredentialsPath))
                && ($fcmCredentialsBase64 === '' || $this->isPlaceholder($fcmCredentialsBase64))
            ) {
                $errors[] = 'Configure FCM_CREDENTIALS_PATH or FCM_CREDENTIALS_BASE64 when PUSH_NOTIFICATIONS_ENABLED=true.';
            }
        }

        $connection = (string) config('database.default');
        $databaseConfig = config("database.connections.$connection");

        if (! is_array($databaseConfig)) {
            $errors[] = "DB_CONNECTION [$connection] is not configured.";
        } elseif ($connection === 'mysql') {
            foreach (['host' => 'DB_HOST', 'database' => 'DB_DATABASE', 'username' => 'DB_USERNAME', 'password' => 'DB_PASSWORD'] as $key => $environmentName) {
                $value = $databaseConfig[$key] ?? null;

                if (! is_string($value) || trim($value) === '' || $this->isPlaceholder($value)) {
                    $errors[] = "$environmentName must contain the production MySQL value.";
                }
            }
        } elseif ($connection === 'sqlite') {
            $database = (string) ($databaseConfig['database'] ?? '');

            if ($database === '' || $database === ':memory:') {
                $errors[] = 'DB_DATABASE must identify a persistent SQLite database in production.';
            }
        }

        return $errors;
    }

    private function decodeAppKey(string $appKey): ?string
    {
        if (! str_starts_with($appKey, 'base64:')) {
            return $appKey !== '' ? $appKey : null;
        }

        $decoded = base64_decode(substr($appKey, 7), true);

        return is_string($decoded) ? $decoded : null;
    }

    private function isPlaceholder(string $value): bool
    {
        $normalized = strtoupper($value);

        return str_contains($normalized, 'CHANGE_ME') || str_contains($normalized, 'REPLACE_WITH');
    }
}
