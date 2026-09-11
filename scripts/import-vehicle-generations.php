<?php

declare(strict_types=1);

use Illuminate\Support\Str;

require dirname(__DIR__).'/vendor/autoload.php';

$source = $argv[1] ?? null;
if (! $source || ! is_file($source)) {
    fwrite(STDERR, "Usage: php scripts/import-vehicle-generations.php /path/to/vehicles.sqlite\n");
    exit(1);
}

$catalog = json_decode(
    file_get_contents(dirname(__DIR__).'/database/data/vehicle_models.json'),
    true,
    flags: JSON_THROW_ON_ERROR,
);
$modelCodes = [];
foreach ($catalog['makes'] as $makeCode => $models) {
    foreach ($models as $model) {
        $modelCodes[$makeCode][normalize($model['name_en'])] = $model['code'];
    }
}

$pdo = new PDO('sqlite:'.$source, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$rows = $pdo->query(<<<'SQL'
    SELECT DISTINCT
        m."group" AS make_code,
        mo.name AS model_name,
        g.name,
        g.year_start,
        g.year_end,
        g.body_type
    FROM generations g
    JOIN models mo ON mo.id = g.model_id
    JOIN makes m ON m.id = mo.make_id
    ORDER BY m."group", mo.name, g.year_start, g.year_end, g.name
    SQL)->fetchAll(PDO::FETCH_ASSOC);

$makes = [];
$matchedModels = [];
$unmatchedModels = [];
foreach ($rows as $row) {
    $makeCode = $row['make_code'];
    $modelCode = $modelCodes[$makeCode][normalize($row['model_name'])] ?? null;
    if (! $modelCode) {
        $unmatchedModels[$makeCode.'|'.$row['model_name']] = true;

        continue;
    }

    $matchedModels[$makeCode.'|'.$modelCode] = true;
    $baseCode = Str::slug($row['name']) ?: 'generation';
    $identity = implode('|', [$row['name'], $row['year_start'], $row['year_end'], $row['body_type']]);
    $code = $baseCode;
    if (isset($makes[$makeCode][$modelCode][$code])) {
        $code .= '-'.substr(sha1($identity), 0, 8);
    }

    $makes[$makeCode][$modelCode][$code] = [
        'code' => $code,
        'name' => $row['name'],
        'start_year' => $row['year_start'] !== null ? (int) $row['year_start'] : null,
        'end_year' => $row['year_end'] !== null ? (int) $row['year_end'] : null,
        'body_type' => $row['body_type'] ?: null,
    ];
}

foreach ($makes as &$models) {
    foreach ($models as &$generations) {
        $generations = array_values($generations);
    }
}
unset($models, $generations);

$commit = trim((string) shell_exec('git -C '.escapeshellarg(dirname($source, 2)).' rev-parse HEAD 2>/dev/null')) ?: null;
$output = [
    '_meta' => [
        'generated_at' => date('Y-m-d'),
        'source' => 'vehicle-makes-models',
        'source_commit' => $commit,
        'license' => 'ODbL-1.0',
        'matched_model_count' => count($matchedModels),
        'generation_count' => array_sum(array_map(
            fn (array $models): int => array_sum(array_map('count', $models)),
            $makes,
        )),
        'unmatched_source_model_count' => count($unmatchedModels),
    ],
    'makes' => $makes,
];

$target = dirname(__DIR__).'/database/data/vehicle_generations.json';
file_put_contents($target, json_encode($output, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n");
fwrite(STDOUT, sprintf(
    "Wrote %d generations for %d catalog models to %s (%d unmatched source models).\n",
    $output['_meta']['generation_count'],
    $output['_meta']['matched_model_count'],
    $target,
    $output['_meta']['unmatched_source_model_count'],
));

function normalize(string $value): string
{
    return Str::of($value)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->squish()->toString();
}
