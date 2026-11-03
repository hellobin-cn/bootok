<?php

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createUnsafeImmutable(__DIR__);
if (file_exists(__DIR__ . '/.env')) {
    $dotenv->load();
}

$env = [
    'adapter' => getenv('DB_CONNECTION') ?: 'mysql',
    'host'    => getenv('DB_HOST') ?: '127.0.0.1',
    'name'    => getenv('DB_DATABASE') ?: 'bootok',
    'user'    => getenv('DB_USERNAME') ?: 'root',
    'pass'    => getenv('DB_PASSWORD') ?: '',
    'charset' => 'utf8mb4',
    'collation' => 'utf8mb4_unicode_ci',
];

// 预检连接：库不存在时自动建库，新装环境无需手动 CREATE DATABASE。
// 已有库直接连通返回，不触发 CREATE 权限检查。
try {
    new PDO("mysql:host={$env['host']};dbname={$env['name']};charset=utf8mb4", $env['user'], $env['pass']);
} catch (PDOException $e) {
    if (!str_contains($e->getMessage(), 'Unknown database')) {
        fwrite(STDERR, '[phinx] 数据库连接失败：' . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    try {
        $pdo = new PDO("mysql:host={$env['host']};charset=utf8mb4", $env['user'], $env['pass']);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$env['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    } catch (PDOException $e2) {
        fwrite(STDERR, '[phinx] 自动建库失败：' . $e2->getMessage() . PHP_EOL);
        exit(1);
    }
}

return [
    'paths' => [
        'migrations' => __DIR__ . '/db/migrations',
        'seeds'      => __DIR__ . '/db/seeds',
    ],
    'environments' => [
        'default_migration_table' => 'phinxlog',
        'default_environment'     => 'dev',
        'dev' => $env,
    ],
];
