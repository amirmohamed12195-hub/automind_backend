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
