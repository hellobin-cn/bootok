<?php

namespace app\middleware;

use Ramsey\Uuid\Uuid;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

class Cross implements MiddlewareInterface
{
    public function process(Request $request, callable $handler): Response
    {
        $request->setHeader('request-id', $request_id = Uuid::uuid4());
        // 如果是options请求则返回一个空响应，否则继续向洋葱芯穿越，并得到一个响应
        $response = $request->method() == 'OPTIONS' ? response('', 204) : $handler($request);

        // JSON 响应统一带 charset（框架 json() 助手默认不带，中文会被部分代理/浏览器误判编码）
        $contentType = $response->getHeader('Content-Type');
        if ($contentType !== null && str_starts_with($contentType, 'application/json') && !str_contains($contentType, 'charset')) {
            $response->header('Content-Type', 'application/json; charset=utf-8');
        }

        // 跨域白名单：CORS_ORIGINS=* 反射任意来源（开发用），生产配置具体域名逗号分隔
        $origin = $request->header('origin', '');
        $allowed = array_map('trim', explode(',', (string)get_env('CORS_ORIGINS', '*')));
        $allowOrigin = null;
        if (in_array('*', $allowed)) {
            $allowOrigin = $origin ?: '*';
        } elseif ($origin !== '' && in_array($origin, $allowed, true)) {
            $allowOrigin = $origin;
        }

        // 非白名单的跨域请求直接拒绝
        if ($origin !== '' && $allowOrigin === null) {
            return response('', 403);
        }

        // 给响应添加跨域相关的http头 + 安全响应头
        $response->withHeaders(array_merge([
            'Access-Control-Allow-Credentials' => 'true',
            'Access-Control-Allow-Methods'     => $request->header('access-control-request-method', '*'),
            'Access-Control-Allow-Headers'     => $request->header('access-control-request-headers', '*'),
            'Access-Control-Expose-Headers'    => 'X-Renewed-Token',
            // 安全响应头：防点击劫持/类型嗅探/引用泄漏
            'X-Frame-Options'                  => 'SAMEORIGIN',
            'X-Content-Type-Options'           => 'nosniff',
            'Referrer-Policy'                  => 'strict-origin-when-cross-origin',
        ], $allowOrigin ? ['Access-Control-Allow-Origin' => $allowOrigin] : []));

        return $response;
    }
}
