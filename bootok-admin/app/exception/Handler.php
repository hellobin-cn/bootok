<?php

namespace app\exception;

use support\Log;
use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\Exception\ExceptionHandler;

/**
 * 全局异常出口
 * 未捕获异常统一渲染为响应契约信封 {code, message, data, request_id}，
 * 与正常流程的返回结构完全一致，前端只需处理一种错误形态
 */
class Handler extends ExceptionHandler
{
    public function report(Throwable $e): void
    {
        Log::error('uncaught exception: ' . $e->getMessage(), [
            'exception' => get_class($e),
            'file'      => $e->getFile() . ':' . $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ]);
    }

    public function render(Request $request, Throwable $e): Response
    {
        [$code, $httpStatus] = $this->mapCode($e);

        $message = config('app.debug')
            ? $e->getMessage() . ' @' . $e->getFile() . ':' . $e->getLine()
            : match ($code) {
                '4004'  => '资源不存在',
                default => '服务异常，请稍后重试',
            };

        $payload = json_encode([
            'code'       => $code,
            'message'    => $message,
            'data'       => null,
            'request_id' => $request->header('request-id', ''),
        ], JSON_UNESCAPED_UNICODE);

        return new Response($httpStatus, ['Content-Type' => 'application/json; charset=utf-8'], $payload);
    }

    /**
     * 异常归类到响应契约的 code
     */
    private function mapCode(Throwable $e): array
    {
        if (is_a($e, \support\exception\PageNotFoundException::class)) {
            return ['4004', 404];
        }
        return ['500', 500];
    }
}
