<?php

declare(strict_types=1);

$arguments = getopt('', ['from:', 'to:']);
$from = strtolower(trim((string) ($arguments['from'] ?? '')));
$to = strtolower(trim((string) ($arguments['to'] ?? '')));

$validHost = static fn (string $host): bool => $host !== ''
    && filter_var('https://'.$host, FILTER_VALIDATE_URL) !== false
    && preg_match('/^[a-z0-9.-]+$/', $host) === 1;

if (! $validHost($from) || ! $validHost($to) || $from === $to) {
    fwrite(STDERR, "Usage: php scripts/migrate-production-domain.php --from=old.example.com --to=new.example.com\n");
    exit(1);
}

$root = dirname(__DIR__);
$environmentPath = $root.'/.env';
if (! is_file($environmentPath)) {
    fwrite(STDERR, "Missing .env in {$root}.\n");
    exit(1);
}

$environment = file_get_contents($environmentPath);
if (! is_string($environment)) {
    fwrite(STDERR, "Unable to read .env.\n");
    exit(1);
}

$changedKeys = [];
foreach (preg_split('/\R/', $environment) ?: [] as $line) {
    if (! str_contains(strtolower($line), $from) || ! preg_match('/^([A-Z][A-Z0-9_]*)=/', $line, $matches)) {
        continue;
    }
    $changedKeys[] = $matches[1];
}

if ($changedKeys === []) {
    fwrite(STDOUT, "No .env values contain {$from}; nothing changed.\n");
    exit(0);
}

$updated = str_ireplace($from, $to, $environment, $replacementCount);
if ($replacementCount < 1) {
    fwrite(STDERR, "Domain replacement failed.\n");
    exit(1);
}

$timestamp = gmdate('Ymd-His');
$backupPath = $root.'/.env.backup-domain-'.$timestamp;
if (! copy($environmentPath, $backupPath)) {
    fwrite(STDERR, "Unable to create the .env backup.\n");
    exit(1);
}
chmod($backupPath, 0600);

$temporaryPath = $root.'/.env.domain-'.$timestamp.'.tmp';
if (file_put_contents($temporaryPath, $updated, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write the temporary .env file.\n");
    exit(1);
}
chmod($temporaryPath, 0600);

if (! rename($temporaryPath, $environmentPath)) {
    @unlink($temporaryPath);
    fwrite(STDERR, "Unable to replace .env. Restore from {$backupPath}.\n");
    exit(1);
}
chmod($environmentPath, 0600);

$changedKeys = array_values(array_unique($changedKeys));
sort($changedKeys);
fwrite(STDOUT, 'Updated domain references in: '.implode(', ', $changedKeys).".\n");
fwrite(STDOUT, "Backup created at {$backupPath}. No secret values were printed.\n");
fwrite(STDOUT, "Next: php artisan config:clear && php artisan automind:check-production-config\n");
