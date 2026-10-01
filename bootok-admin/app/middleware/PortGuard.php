<?php

namespace app\middleware;

use app\enum\Code;
use support\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 端口门禁基类
 * admin与client进程组共享路由表，此中间件确保路由只响应指定端口的请求，
 * 防止弹性机组（仅开放client端口）暴露管理端接口面
 */
abstract class PortGuard implements MiddlewareInterface
{
    /** 子类返回允许的本地端口 */
    abstract protected function allowedPort(): int;

    public function process(Request|\Webman\Http\Request $request, callable $handler): Response
    {
        if ($request->getLocalPort() !== $this->allowedPort()) {
            return json_error($request->header('request-id', ''), Code::NOT_FOUND->getDescription(), Code::NOT_FOUND->value);
        }
        return $handler($request);
    }

    /**
     * 从 process 配置解析监听端口，改端口无需改中间件
     */
    protected function portOf(string $process): int
    {
        $listen = config("process.$process.listen", '');
        return (int)(parse_url($listen, PHP_URL_PORT) ?: 0);
    }
}
