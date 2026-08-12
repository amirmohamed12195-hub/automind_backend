#!/usr/bin/env sh

set -eu

AUTOMIND_PHP_BINARY="${AUTOMIND_PHP_BINARY:-php}"

if ! command -v "$AUTOMIND_PHP_BINARY" >/dev/null 2>&1; then
    echo "PHP binary not found: $AUTOMIND_PHP_BINARY" >&2
    echo "Set AUTOMIND_PHP_BINARY to the absolute PHP 8.3+ CLI path." >&2
    exit 1
fi

if [ ! -f artisan ] || [ ! -f composer.json ]; then
    echo "Run this script from the AutoMind backend project root." >&2
    exit 1
fi

if [ ! -f vendor/autoload.php ]; then
    echo "Missing vendor/autoload.php; run composer install first." >&2
    exit 1
fi

if [ ! -f public/build/manifest.json ]; then
    echo "Missing public/build/manifest.json. Build and commit frontend assets before deployment." >&2
    exit 1
fi

"$AUTOMIND_PHP_BINARY" artisan config:clear --ansi
"$AUTOMIND_PHP_BINARY" artisan automind:check-production-config --ansi
"$AUTOMIND_PHP_BINARY" artisan automind:check-provider-config --ansi
"$AUTOMIND_PHP_BINARY" artisan automind:check-billing-config --ansi
"$AUTOMIND_PHP_BINARY" artisan migrate --force --ansi
"$AUTOMIND_PHP_BINARY" artisan db:seed --class='Database\Seeders\ReferenceDataSeeder' --force --ansi
"$AUTOMIND_PHP_BINARY" artisan optimize:clear --ansi
"$AUTOMIND_PHP_BINARY" artisan optimize --ansi
"$AUTOMIND_PHP_BINARY" artisan queue:restart --ansi

echo "AutoMind production deployment completed."
