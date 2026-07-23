<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\MySqlConnection;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$connection = new MySqlConnection(new PDO('sqlite::memory:'), 'automind', '', [
    'driver' => 'mysql', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix_indexes' => true, 'strict' => true,
]);
$connection->setEventDispatcher($app['events']);
$connection->useDefaultQueryGrammar();
$connection->useDefaultSchemaGrammar();
$connection->useDefaultPostProcessor();

$app['db']->extend('schema_export', fn () => $connection);
config(['database.default' => 'schema_export', 'database.connections.schema_export' => ['driver' => 'schema_export']]);
DB::setDefaultConnection('schema_export');

$migrationFiles = glob(dirname(__DIR__).'/database/migrations/*.php') ?: [];
$queries = $connection->pretend(function () use ($migrationFiles): void {
    foreach ($migrationFiles as $migrationFile) {
        $migration = require $migrationFile;
        $migration->up();
    }
});

$sql = [
    '-- AutoMind AI MySQL 8 schema export. Generated from Laravel migrations.',
    '-- Includes Laravel migration state so fresh installs may safely load this snapshot.',
    '-- Regenerate with: php scripts/export-mysql-schema.php',
    'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;',
    'SET FOREIGN_KEY_CHECKS=0;',
];
foreach ($queries as $query) {
    $statement = trim((string) $query['query']);
    if ($statement !== '') {
        $sql[] = rtrim($statement, ';').';';
    }
}
$sql[] = 'create table `migrations` (`id` int unsigned not null auto_increment primary key, `migration` varchar(255) not null, `batch` int not null) default character set utf8mb4 collate \'utf8mb4_unicode_ci\';';
foreach ($migrationFiles as $migrationFile) {
    $migration = str_replace("'", "''", basename($migrationFile, '.php'));
    $sql[] = "insert into `migrations` (`migration`, `batch`) values ('$migration', 1);";
}
$sql[] = 'SET FOREIGN_KEY_CHECKS=1;';
$sql[] = '';

$targetDirectory = dirname(__DIR__).'/database/schema';
if (! is_dir($targetDirectory)) {
    mkdir($targetDirectory, 0755, true);
}
$contents = implode(PHP_EOL, $sql);
$target = $targetDirectory.'/mysql-schema.sql';
if (in_array('--check', $argv, true)) {
    if (! is_file($target) || file_get_contents($target) !== $contents) {
        fwrite(STDERR, "database/schema/mysql-schema.sql is stale. Regenerate it with php scripts/export-mysql-schema.php.\n");
        exit(1);
    }
    fwrite(STDOUT, sprintf('Schema export matches all migrations (%d statements).%s', count($queries), PHP_EOL));
    exit(0);
}
file_put_contents($target, $contents);
fwrite(STDOUT, sprintf('Wrote %d migration statements and %d migration records to database/schema/mysql-schema.sql%s', count($queries), count($migrationFiles), PHP_EOL));
