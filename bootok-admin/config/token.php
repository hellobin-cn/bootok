<?php

/**
 * Token 渠道化配置
 * 每个渠道独立：键前缀（Redis 单点隔离）、JWT密钥、签发者、有效期、滑动续期阈值
 * 管理端走 admin 渠道；用户端将来启用 client 渠道（ClientTokenService extends TokenService）
 */
return [
    // 渠道公共
    'alg'          => 'HS256',
    'leeway'       => (int)get_env('JWT_LEEWAY', 10),
    // 续期换发后旧token的宽限秒数（覆盖续期瞬间的并发请求）
    'renew_grace'  => 60,

    'channels' => [
        'admin' => [
            'prefix'          => 'admin-u',
            // 不设置 ADMIN_JWT_KEY 时回退 JWT_KEY（首次切换前缀会使旧token失效，重新登录即可）
            'key'             => get_env('ADMIN_JWT_KEY', get_env('JWT_KEY', '')),
            'iss'             => get_env('JWT_ISS', ''),
            'aud'             => get_env('JWT_AUD', ''),
            'exp'             => (int)get_env('ACCESS_TOKEN_EXP', 7200),
            'renew_threshold' => (int)get_env('ADMIN_TOKEN_RENEW_THRESHOLD', 1800),
        ],
        // 用户端渠道预留：ClientTokenService extends TokenService 后即启用
        'client' => [
            'prefix'          => 'client-u',
            'key'             => get_env('JWT_KEY', ''),
            'iss'             => get_env('JWT_ISS', ''),
            'aud'             => get_env('JWT_AUD', ''),
            'exp'             => (int)get_env('CLIENT_ACCESS_TOKEN_EXP', 2592000),
            'renew_threshold' => (int)get_env('CLIENT_TOKEN_RENEW_THRESHOLD', 0),
        ],
    ],
];
