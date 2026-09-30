<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

use Illuminate\Database\Capsule\Manager;

$database = new Manager;
$database->addConnection([
    'driver' => 'pgsql',
    'host' => getenv('PGHOST') ?: '/tmp',
    'port' => getenv('PGPORT') ?: '5432',
    'database' => getenv('PGDATABASE') ?: 'postgres',
    'username' => getenv('PGUSER') ?: 'postgres',
    'password' => getenv('PGPASSWORD') ?: '',
]);

// Read-only derived rows distinguish prepared bindings from SQL integer literals.
foreach ([1, true, 0, false] as $value) {
    $rows = $database->getConnection()->query()
        ->fromRaw('(select true as active) as sample')
        ->where('active', $value)
        ->get();

    if ($rows->count() !== (($value === 1 || $value === true) ? 1 : 0)) {
        throw new RuntimeException('Unexpected PostgreSQL boolean binding result.');
    }
}

echo 'PostgreSQL boolean bindings passed.'.PHP_EOL;
