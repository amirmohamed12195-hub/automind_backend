<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ConfigureAdminCredentials extends Command
{
    protected $signature = 'automind:configure-admin {--username=admin : Administrator username}';

    protected $description = 'Securely configure the web administrator username and password in .env';

    public function handle(): int
    {
        $username = trim((string) $this->option('username'));

        if (preg_match('/^[A-Za-z0-9._-]{3,64}$/', $username) !== 1) {
            $this->error('The admin username must be 3-64 letters, numbers, dots, underscores, or hyphens.');

            return self::FAILURE;
        }

        $password = $this->secret('Admin password');
        $confirmation = $this->secret('Confirm admin password');

        if (! is_string($password) || $password === '' || ! hash_equals($password, (string) $confirmation)) {
            $this->error('The administrator passwords are empty or do not match.');

            return self::FAILURE;
        }

        if (strlen($password) < 12) {
            $this->warn('This administrator password is weak. Use at least 12 characters in production.');
        }

        $environmentPath = base_path('.env');
        $environment = is_file($environmentPath) ? file_get_contents($environmentPath) : false;

        if (! is_string($environment)) {
            $this->error('Unable to read the application .env file.');

            return self::FAILURE;
        }

        $environment = $this->replaceEnvironmentValue($environment, 'ADMIN_WEB_USERNAME', $username);
        $environment = $this->replaceEnvironmentValue(
            $environment,
            'ADMIN_WEB_PASSWORD_HASH',
            password_hash($password, PASSWORD_BCRYPT),
        );

        $temporaryPath = base_path('.env.admin-'.bin2hex(random_bytes(6)));

        if (file_put_contents($temporaryPath, $environment, LOCK_EX) === false) {
            $this->error('Unable to write the updated administrator credentials.');

            return self::FAILURE;
        }

        chmod($temporaryPath, 0600);

        if (! rename($temporaryPath, $environmentPath)) {
            @unlink($temporaryPath);
            $this->error('Unable to replace the application .env file.');

            return self::FAILURE;
        }

        $this->callSilently('config:clear');
        $this->info("Administrator [$username] configured. The password was stored only as a hash.");

        return self::SUCCESS;
    }

    private function replaceEnvironmentValue(string $environment, string $key, string $value): string
    {
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';

        if (preg_match($pattern, $environment) === 1) {
            $updated = preg_replace_callback(
                $pattern,
                static fn (): string => "$key=$value",
                $environment,
                1,
            );

            return is_string($updated) ? $updated : $environment;
        }

        return rtrim($environment).PHP_EOL."$key=$value".PHP_EOL;
    }
}
