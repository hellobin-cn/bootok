<?php

namespace app\model;

use app\attribute\AutoResource;

/**
 * 登录日志（只读资源，成功与失败均记录）
 */
#[AutoResource(name: '登录日志', only: ['getRows'], read: 'login-log.rows')]
class LoginLog extends Model
{
    protected $table = 'sys_login_log';

    // created_at 由数据库默认值生成
    public $timestamps = false;
}
