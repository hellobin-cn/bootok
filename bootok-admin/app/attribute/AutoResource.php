<?php

namespace app\attribute;

use Attribute;

/**
 * 自动资源注解
 * 标注了此注解的模型才会被 /auto/{model} 参数化路由解析，未标注的模型不暴露任何接口
 */
#[Attribute(Attribute::TARGET_CLASS)]
class AutoResource
{
    /** 全部可生成的动作 */
    public const ACTIONS = ['create', 'getAll', 'getRows', 'getForm', 'getItem', 'edit', 'delete', 'deleteFile', 'export', 'batchDelete', 'trash', 'restore', 'forceDelete', 'import', 'importTemplate'];

    /** 实际允许的动作 */
    public array $actions;

    public function __construct(
        public string $name = '',
        public string $path = '',
        array         $only = [],
        array         $except = [],
        public string $read = ''
    )
    {
        $this->actions = $only ?: self::ACTIONS;
        if ($except) {
            $this->actions = array_values(array_diff($this->actions, $except));
        }
    }

    public function allows(string $action): bool
    {
        return in_array($action, $this->actions);
    }

    /**
     * 读操作是否需要权限点校验
     * $read 为空：任何登录管理员可读（业务模型默认，页面可见性由菜单控制）
     * $read 非空：读操作需要 purview 含该权限点（如 'admin.rows'），用于日志/配置/账号等敏感模型
     */
    public function readMark(): string
    {
        return strtolower($this->read);
    }
}
