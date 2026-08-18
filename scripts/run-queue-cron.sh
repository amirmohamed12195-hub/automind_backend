#!/usr/bin/env sh

set -eu

AUTOMIND_PROJECT_ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
AUTOMIND_PHP_BINARY="${AUTOMIND_PHP_BINARY:-/usr/bin/php}"

cd "$AUTOMIND_PROJECT_ROOT"

exec "$AUTOMIND_PHP_BINARY" artisan queue:work database \
    --queue=media-processing,diagnostic-ai,price-search,notifications,maintenance-reminders \
    --sleep=1 \
    --tries=4 \
    --timeout=240 \
    --stop-when-empty
