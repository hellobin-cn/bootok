<?php

namespace app\middleware;

use app\enum\Code;
use app\model\Admin;
use app\service\admin\AdminTokenService;
use DI\Attribute\Inject;
use support\Cache;
use support\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 认证中间件
 * 校验JWT与Redis单点记录；权限上下文（组/超管标志）实时反查数据库（短缓存），
 * 管理员改组、撤销超管、被删除后在缓存过期内生效，不信任token内嵌的权限声明。
 * token 即将过期时自动滑动续期，通过 X-Renewed-Token 响应头下发新token
 */
class Access implements MiddlewareInterface
{
    /** 管理员上下文缓存秒数；Admin 模型保存/删除时会主动清除 */
    private const INFO_CACHE_TTL = 60;

    #[Inject]
    private ?AdminTokenService $tokenService = null;

    public function process(Request|\Webman\Http\Request $request, callable $handler): Response
    {
        $token = str_replace('Bearer ', '', $request->header('Authorization', ''));
        $data = new \stdClass();
        if ($token && $this->tokenService->verify($token, $data)) {
            $info = $this->adminInfo((int)$data->sub);
            if (!$info) {
                return json_error($request->header('request-id', ''), '账号不存在或已禁用', Code::UNAUTHORIZED->value);
            }
            $request->setCustomData('adminId', $info['adminId']);
            $request->setCustomData('adminName', $info['adminName']);
            $request->setCustomData('groupId', $info['groupId']);
            $request->setCustomData('isSuper', $info['isSuper']);

            $response = $handler($request);
            // 滑动续期：新token通过响应头下发，前端拦截器负责替换本地存储
            $newToken = $this->tokenService->renew($token, $data);
            if ($newToken) {
                $response->header('X-Renewed-Token', $newToken);
            }
            return $response;
        }
        return json_error($request->header('request-id', ''), Code::UNAUTHORIZED->getDescription(), Code::UNAUTHORIZED->value);
    }

    /**
     * 管理员实时信息（短缓存，Admin 模型变更时失效）
     */
    private function adminInfo(int $adminId): ?array
    {
        $key = 'admin-info-' . $adminId;
        $cached = Cache::get($key);
        if ($cached !== null) {
            return json_decode($cached, true) ?: null;
        }
        $admin = Admin::find($adminId);
        if (!$admin) {
            return null;
        }
        $info = [
            'adminId'   => $admin->admin_id,
            'adminName' => $admin->admin_name,
            'groupId'   => $admin->group_id,
            'isSuper'   => $admin->is_super,
        ];
        Cache::set($key, json_encode($info), self::INFO_CACHE_TTL);
        return $info;
    }
}
