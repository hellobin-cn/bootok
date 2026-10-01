<?php

namespace app\middleware;

use app\enum\Code;
use app\validate\Validate as Validator;
use support\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;

/**
 * 验证中间件
 * 约定：自动资源路由按路由模型段、专用控制器按控制器短名，查找 app\validate\{Name}，scene 名 = action 名
 */
class Validate implements MiddlewareInterface
{
    public function process(Request|\Webman\Http\Request $request, callable $handler): Response
    {
        $name = $request->route?->param('model');
        if ($name) {
            $name = str_replace(' ', '', ucwords(str_replace('-', ' ', $name)));
        } else {
            // 控制器短名：取类名最后一段并去掉 Controller 后缀（兼容子命名空间，
            // 写死 \app\controller\ 前缀在控制器移入子目录时会解析错）
            $controller = $request->controller ?? '';
            $name = preg_replace('/Controller$/', '', substr($controller, (int)strrpos($controller, '\\') + 1));
        }
        $class = 'app\\validate\\' . $name;
        if ($name && class_exists($class) && $request->action) {
            /** @var Validator $validator */
            $validator = new $class();
            if ($validator->hasScene($request->action)) {
                // 路由参数并入校验数据（如编辑时的 id），供唯一性等规则排除自身
                $data = $request->all();
                foreach (($request->route?->param() ?? []) as $paramKey => $paramValue) {
                    if (!isset($data[$paramKey])) {
                        $data[$paramKey] = $paramValue;
                    }
                }
                if (!$validator->scene($request->action)->check($data)) {
                    return json_error($request->header('request-id', ''), $validator->getError(), Code::PARAM_VALIDATE->value);
                }
            }
        }
        return $handler($request);
    }
}
