<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

// CLI-only deployment step for shared hosts that publish Git without artisan.
// Runs this release's backward-compatible migrations and clears stale caches.
// A hash marker makes a repeated cron invocation a no-op after success.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

chdir(dirname(__DIR__));
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$inputs = [__FILE__, 'routes/web.php', 'routes/console.php', 'config/public.php', 'config/services.php', 'config/kashier.php', 'routes/api.php', 'app/Http/Controllers/Api/V1/AuthController.php', 'app/Http/Requests/VehicleRequest.php', 'database/migrations/2026_10_22_000100_make_vehicle_details_optional.php'];
$stamp = hash('sha256', implode('', array_map(fn ($file) => hash_file('sha256', $file), $inputs)));
$marker = storage_path('app/store-release-20261016.json');
$lock = fopen(storage_path('app/store-release-20261016.lock'), 'c');
if (! $lock || ! flock($lock, LOCK_EX | LOCK_NB)) {
    exit(1);
}
try {
    if (! is_file($marker) || (json_decode(file_get_contents($marker), true)['stamp'] ?? null) !== $stamp) {
        foreach ([
            ['config:clear', []],
            ['migrate', ['--force' => true, '--path' => 'database/migrations/2026_10_16_000001_create_apple_authorizations_table.php']],
            ['migrate', ['--force' => true, '--path' => 'database/migrations/2026_10_19_200000_create_kashier_orders_table.php']],
            ['migrate', ['--force' => true, '--path' => 'database/migrations/2026_10_22_000100_make_vehicle_details_optional.php']],
            ['route:clear', []], ['view:clear', []], ['queue:restart', []],
        ] as [$command, $arguments]) {
            if ($kernel->call($command, $arguments) !== 0) {
                throw new RuntimeException('Release step failed: '.$command);
            }
        }
        file_put_contents($marker, json_encode(['stamp' => $stamp, 'completedAt' => gmdate(DATE_ATOM)]), LOCK_EX);
    }
    $columns = collect(Schema::getColumns('vehicles'))->keyBy('name');
    $vehicleDetailsOptional = collect(['engine', 'fuel_type', 'transmission', 'mileage_km'])
        ->every(fn ($column) => ($columns->get($column)['nullable'] ?? false) === true);
    if (! $vehicleDetailsOptional) {
        throw new RuntimeException('Optional vehicle columns are not ready.');
    }
    $keyPath = config('services.apple.sign_in_private_key_path');
    echo json_encode([
        'release' => '2026-10-22',
        'checkedAt' => gmdate(DATE_ATOM),
        'vehicleDetailsOptional' => $vehicleDetailsOptional,
        'vehicleMigrationApplied' => DB::table('migrations')->where('migration', '2026_10_22_000100_make_vehicle_details_optional')->exists(),
        'schemaReady' => Schema::hasTable('apple_authorizations'),
        'refundRouteReady' => Route::has('refunds'),
        'kashierSchemaReady' => Schema::hasTable('kashier_orders'),
        'kashierRouteReady' => Route::has('billing.index'),
        'appleSignInKeyConfigured' => filled(config('services.apple.sign_in_key_id')) && is_string($keyPath) && is_readable($keyPath),
        'appleTeamConfigured' => filled(config('services.apple.team_id')),
    ], JSON_PRETTY_PRINT).PHP_EOL;
} catch (Throwable) {
    // Never print production exception messages, environment values, or tokens.
    fwrite(STDERR, "Store release preparation failed; inspect the private server logs.\n");
    exit(1);
} finally {
    flock($lock, LOCK_UN);
    fclose($lock);
}
