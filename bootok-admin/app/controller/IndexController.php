<?php

namespace app\controller;

use app\model\Admin;
use app\service\PermissionService;
use support\Response;

class IndexController extends Controller
{
    public function index(): Response
    {
        return $this->success(['name' => config('app.name')]);
    }

    /**
     * 当前管理员信息与权限集合
     * purview 同时驱动前端路由/菜单过滤与后端写操作权限点校验
     */
    public function getUserInfo(): Response
    {
        $admin = Admin::find($this->adminId);
        if (!$admin) {
            return $this->error('账号不存在', '4001');
        }
        $purview = [];
        if (!$this->isSuper) {
            $purview = (new PermissionService())->purview($this->groupId);
        }
        return $this->success([
            'admin_name' => $admin->admin_name,
            'is_super'   => $admin->is_super,
            'purview'    => $purview,
        ]);
    }
}
