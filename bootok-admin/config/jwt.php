<?php
return [
    'key'               => get_env('JWT_KEY', '5ad66a3fb9d6a5d1b97340e8e82a74d0'),
    'iss'               => get_env('JWT_ISS', ''),
    'aud'               => get_env('JWT_AUD', ''),
    'leeway'            => get_env('JWT_LEEWAY', 0),
    'access_token_exp'  => get_env('ACCESS_TOKEN_EXP', 7200),
    'refresh_token_exp' => get_env('REFRESH_TOKEN_EXP', 86400),
    // 剩余有效期低于该秒数时自动滑动续期（响应头 X-Renewed-Token 下发新token），0为关闭
    'token_renew_threshold' => (int)get_env('TOKEN_RENEW_THRESHOLD', 1800),
];