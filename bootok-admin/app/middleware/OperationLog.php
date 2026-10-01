<?php

namespace app\middleware;

use app\model\OperationLog as OperationLogModel;
use support\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 操作日志中间件
 * 只记录已登录管理员的写操作（POST/PUT/DELETE/PATCH），日志失败不阻断业务
 */
class OperationLog implements MiddlewareInterface
{
    /** 需要脱敏的参数名 */
    private const SENSITIVE_FIELDS = ['password', 'oldPassword', 'newPassword', 'rePassword', 'adminPassword'];

    public function process(Request|\Webman\Http\Request $request, callable $handler): Response
    {
        $adminId = $request->getCustomData('adminId', 0);
        if ($adminId && in_array($request->method(), ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            try {
                $params = $request->all();
                foreach (self::SENSITIVE_FIELDS as $field) {
                    if (isset($params[$field])) {
                        $params[$field] = '***';
                    }
                }
                OperationLogModel::create([
                    'admin_id'   => $adminId,
                    'admin_name' => $request->getCustomData('adminName', ''),
                    'method'     => $request->method(),
                    'path'       => $request->path(),
                    'ip'         => $request->getRealIp(),
                    'params'     => json_encode($params, JSON_UNESCAPED_UNICODE),
                ]);
            } catch (\Throwable $e) {
                support\Log::error('操作日志写入失败: ' . $e->getMessage());
                // 日志写入失败不影响业务
            }
        }
        return $handler($request);
    }
}
