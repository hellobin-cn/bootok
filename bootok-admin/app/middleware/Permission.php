<?php

namespace app\middleware;

use app\enum\Code;
use app\service\ModelResolver;
use app\service\PermissionService;
use DI\Attribute\Inject;
use support\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 权限中间件
 * 权限点格式：{模型kebab路径或控制器短名}.{action}，如 article.create
 * 读操作默认放行（页面可见性由前端菜单控制）；模型注解声明 read 权限点时（如 admin.rows），读操作同样校验
 * 写操作一律校验 purview；超级管理员（admin_id=1 或 is_super）直接放行
 */
class Permission implements MiddlewareInterface
{
    /** 默认放行的读动作 */
    private const READ_ACTIONS = ['getRows', 'getItem', 'getForm', 'getAll'];

    #[Inject]
    private ?PermissionService $permissionService = null;

    public function process(Request|\Webman\Http\Request $request, callable $handler): Response
    {
        $adminId = $request->getCustomData('adminId', 0);
        $isSuper = $request->getCustomData('isSuper', 0);
        if ($adminId == 1 || $isSuper) {
            return $handler($request);
        }

        $subject = $request->route?->param('model');
        if (!$subject) {
            // 控制器短名：取类名最后一段并去掉 Controller 后缀（兼容子命名空间）
            $controller = $request->controller ?? '';
            $subject = preg_replace('/Controller$/', '', substr($controller, (int)strrpos($controller, '\\') + 1));
        }

        // 读操作：模型声明了 read 权限点时校验，否则放行
        if (in_array($request->action, self::READ_ACTIONS)) {
            $class = $subject ? ModelResolver::resolve($subject) : null;
            $readMark = $class ? ModelResolver::attribute($class)->readMark() : '';
            if ($readMark === '' || $this->permissionService->check($request->getCustomData('groupId', 0), $readMark)) {
                return $handler($request);
            }
            return json_error($request->header('request-id', ''), Code::FORBIDDEN->getDescription(), Code::FORBIDDEN->value);
        }

        // 写操作：一律校验权限点
        $mark = strtolower($subject . '.' . $request->action);
        if (!$this->permissionService->check($request->getCustomData('groupId', 0), $mark)) {
            return json_error($request->header('request-id', ''), Code::FORBIDDEN->getDescription(), Code::FORBIDDEN->value);
        }
        return $handler($request);
    }
}
