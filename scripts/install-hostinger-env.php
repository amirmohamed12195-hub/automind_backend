<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$templatePath = $root.'/.env.hostinger.example';
$environmentPath = $root.'/.env';

if (! is_file($templatePath)) {
    fwrite(STDERR, "Missing .env.hostinger.example.\n");
    exit(1);
}

$existingEnvironment = is_file($environmentPath)
    ? file_get_contents($environmentPath)
    : '';

if ($existingEnvironment === false) {
    fwrite(STDERR, "Unable to read the existing .env file.\n");
    exit(1);
}

$appKey = null;

if (preg_match('/^APP_KEY=(.*)$/m', $existingEnvironment, $matches) === 1) {
    $candidate = trim($matches[1]);

    if ($candidate !== '') {
        $appKey = $candidate;
    }
}

$appKey ??= 'base64:'.base64_encode(random_bytes(32));

$environment = file_get_contents($templatePath);

if ($environment === false) {
    fwrite(STDERR, "Unable to read .env.hostinger.example.\n");
    exit(1);
}

$environment = preg_replace(
    '/^APP_KEY=.*$/m',
    'APP_KEY='.$appKey,
    $environment,
    1,
);

if (! is_string($environment)) {
    fwrite(STDERR, "Unable to prepare the Hostinger environment file.\n");
    exit(1);
}

$openAiApiKey = getenv('AUTOMIND_OPENAI_API_KEY');
$databasePassword = getenv('AUTOMIND_DB_PASSWORD');

if (! is_string($openAiApiKey) || trim($openAiApiKey) === '') {
    fwrite(STDERR, "AUTOMIND_OPENAI_API_KEY is required.\n");
    exit(1);
}

if (! is_string($databasePassword) || $databasePassword === '') {
    fwrite(STDERR, "AUTOMIND_DB_PASSWORD is required.\n");
    exit(1);
}

$openAiApiKey = trim($openAiApiKey);

if (
    preg_match('/[\r\n]/', $openAiApiKey) === 1
    || str_contains($openAiApiKey, 'OPENAI_BASE_URL=')
) {
    fwrite(STDERR, "The supplied OpenAI API key is malformed.\n");
    exit(1);
}

if (preg_match('/[\r\n]/', $databasePassword) === 1) {
    fwrite(STDERR, "The supplied database password contains an invalid newline.\n");
    exit(1);
}

$environment = preg_replace_callback(
    '/^OPENAI_API_KEY=.*$/m',
    static fn (): string => 'OPENAI_API_KEY='.$openAiApiKey,
    $environment,
    1,
);

$environment = preg_replace_callback(
    '/^DB_PASSWORD=.*$/m',
    static fn (): string => 'DB_PASSWORD='.$databasePassword,
    $environment,
    1,
);

if (! is_string($environment)) {
    fwrite(STDERR, "Unable to set the production credentials.\n");
    exit(1);
}

if (is_file($environmentPath)) {
    $backupPath = $root.'/.env.backup-'.gmdate('Ymd-His');

    if (! copy($environmentPath, $backupPath)) {
        fwrite(STDERR, "Unable to back up the existing .env file.\n");
        exit(1);
    }

    chmod($backupPath, 0600);
    fwrite(STDOUT, "Existing .env backed up to {$backupPath}.\n");
}

$temporaryPath = $root.'/.env.tmp-'.bin2hex(random_bytes(6));

if (file_put_contents($temporaryPath, $environment, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write the replacement environment file.\n");
    exit(1);
}

chmod($temporaryPath, 0600);

if (! rename($temporaryPath, $environmentPath)) {
    @unlink($temporaryPath);
    fwrite(STDERR, "Unable to replace the environment file.\n");
    exit(1);
}

fwrite(
    STDOUT,
    "Hostinger .env installed and the existing APP_KEY preserved.\n".
    "The supplied MySQL and OpenAI credentials were installed.\n".
    "Next, run: php artisan automind:configure-admin --username=admin\n",
);
