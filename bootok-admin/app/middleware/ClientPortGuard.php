<?php

namespace app\middleware;

/**
 * 用户端端口门禁：只响应 client 进程（默认8788）上的请求
 */
class ClientPortGuard extends PortGuard
{
    protected function allowedPort(): int
    {
        return $this->portOf('client');
    }
}
