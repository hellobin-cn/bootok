<?php

namespace app\service\admin;

use app\service\TokenService;

/**
 * 管理端 token 渠道
 * 键前缀 admin-u 与用户端隔离，密钥/有效期见 config/token.php admin 段
 */
class AdminTokenService extends TokenService
{
    protected function channel(): string
    {
        return 'admin';
    }
}
