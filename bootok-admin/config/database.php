<?php
return [
    'default'     => get_env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver'    => 'mysql',
            'host'      => get_env('DB_HOST', '127.0.0.1'),
            'port'      => get_env('DB_PORT', '3306'),
            'database'  => get_env('DB_DATABASE', 'your_database'),
            'username'  => get_env('DB_USERNAME', 'your_username'),
            'password'  => get_env('DB_PASSWORD', 'your_password'),
            'charset'   => get_env('DB_CHARSET', 'utf8mb4'),
            'collation' => get_env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix'    => get_env('DB_PREFIX', ''),
            'strict'    => true,
            'engine'    => null,
            'options'   => [
                PDO::ATTR_EMULATE_PREPARES => false, // Must be false for Swoole and Swow drivers.
            ],
            'pool'      => [
                'max_connections'    => 5,
                'min_connections'    => 1,
                'wait_timeout'       => 3,
                'idle_timeout'       => 60,
                'heartbeat_interval' => 50,
            ],
        ],
    ],
];