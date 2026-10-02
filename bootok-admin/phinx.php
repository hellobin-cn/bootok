<?php

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__);
if (file_exists(__DIR__ . '/.env')) {
    $dotenv->load();
}

return [
    'paths' => [
        'migrations' => __DIR__ . '/db/migrations',
        'seeds'      => __DIR__ . '/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'dev',
        'dev' => [
            'adapter' => getenv('DB_CONNECTION') ?: 'mysql',
            'host'    => getenv('DB_HOST') ?: '127.0.0.1',
            'name'    => getenv('DB_DATABASE') ?: 'bootok',
            'user'    => getenv('DB_USERNAME') ?: 'root',
            'pass'    => getenv('DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
        ],
    ],
];
