<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$normalise = static fn (string $path): string => preg_replace('/\{[^}]+}/', '{}', '/'.ltrim(preg_replace('#^api/v1/?#', '', $path), '/'));
$actual = [];
foreach ($app['router']->getRoutes() as $route) {
    if (! str_starts_with($route->uri(), 'api/v1/')) {
        continue;
    }
    foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
        $actual[] = strtolower($method).' '.$normalise($route->uri());
    }
}

$documented = [];
$path = null;
foreach (file(dirname(__DIR__).'/docs/openapi.yaml', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
    if (preg_match('/^  (\/[^:]+):$/', $line, $matches)) {
        $path = $matches[1];
    } elseif ($path !== null && preg_match('/^    (get|post|put|patch|delete):/', $line, $matches)) {
        $documented[] = $matches[1].' '.$normalise($path);
    }
}

sort($actual);
sort($documented);
$missingFromOpenApi = array_values(array_diff($actual, $documented));
$missingFromApplication = array_values(array_diff($documented, $actual));
if ($missingFromOpenApi !== [] || $missingFromApplication !== []) {
    fwrite(STDERR, "API contract mismatch.\n");
    foreach ($missingFromOpenApi as $operation) {
        fwrite(STDERR, "Missing from OpenAPI: $operation\n");
    }
    foreach ($missingFromApplication as $operation) {
        fwrite(STDERR, "Missing from application: $operation\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf("API contract is complete: %d operations across %d paths.\n", count($actual), count(array_unique(array_map(fn (string $operation): string => explode(' ', $operation, 2)[1], $actual)))));
