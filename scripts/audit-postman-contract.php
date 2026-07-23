<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$normalise = static fn (string $path): string => preg_replace('/(?:\{[^}]+}|:[^\/]+)/', '{}', '/'.ltrim(preg_replace('#^api/v1/?#', '', $path), '/'));
$actual = [];
foreach ($app['router']->getRoutes() as $route) {
    if (! str_starts_with($route->uri(), 'api/v1/')) {
        continue;
    }
    foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
        $actual[] = strtolower($method).' '.$normalise($route->uri());
    }
}

$collectionPath = dirname(__DIR__).'/docs/postman/AutoMind.postman_collection.json';
$collection = json_decode((string) file_get_contents($collectionPath), true, 512, JSON_THROW_ON_ERROR);
$postman = [];
$visit = function (array $items) use (&$visit, &$postman, $normalise): void {
    foreach ($items as $item) {
        if (isset($item['request']) && is_array($item['request'])) {
            $segments = $item['request']['url']['path'] ?? [];
            $path = implode('/', array_map(fn ($segment) => is_array($segment) ? (string) ($segment['value'] ?? '') : (string) $segment, is_array($segments) ? $segments : []));
            $postman[] = strtolower((string) ($item['request']['method'] ?? '')).' '.$normalise($path);
        } elseif (isset($item['item']) && is_array($item['item'])) {
            $visit($item['item']);
        }
    }
};
$visit($collection['item'] ?? []);

sort($actual);
sort($postman);
$missingFromPostman = array_values(array_diff($actual, $postman));
$missingFromApplication = array_values(array_diff($postman, $actual));
if ($missingFromPostman !== [] || $missingFromApplication !== []) {
    fwrite(STDERR, "Postman contract mismatch.\n");
    foreach ($missingFromPostman as $operation) {
        fwrite(STDERR, "Missing from Postman: $operation\n");
    }
    foreach ($missingFromApplication as $operation) {
        fwrite(STDERR, "Missing from application: $operation\n");
    }
    exit(1);
}

fwrite(STDOUT, sprintf('Postman contract is complete: %d requests.%s', count($postman), PHP_EOL));
