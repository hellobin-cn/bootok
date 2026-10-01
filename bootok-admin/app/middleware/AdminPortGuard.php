<?php

namespace app\middleware;

/**
 * 管理端端口门禁：只响应 admin 进程（默认8787）上的请求
 */
class AdminPortGuard extends PortGuard
{
    protected function allowedPort(): int
    {
        return $this->portOf('admin');
    }
}
