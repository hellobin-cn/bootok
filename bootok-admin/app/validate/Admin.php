<?php

namespace app\validate;

/**
 * 管理员验证器
 * 同时服务于 AdminController（login/updatePassword）与 /auto/admin 资源（create/edit）
 */
class Admin extends Validate
{
    protected array $rule = [
        'username'       => 'require|max:50',
        'password'       => 'require',
        'old_password'   => 'require',
        'new_password'   => 'require|checkNewPassword',
        're_password'    => 'require|confirm:new_password',
        'admin_name'     => 'require|max:50|checkAdminName',
        'admin_password' => 'checkAdminPassword',
        'group_id'       => 'require|integer',
    ];

    protected array $message = [
        'username.require'     => '请输入用户名',
        'username.max'         => '用户名不超过50个字符',
        'password.require'     => '请输入密码',
        'old_password.require' => '请输入原密码',
        'new_password.require' => '请输入新密码',
        'new_password.min'     => '新密码不能少于6位',
        're_password.require'  => '请重复输入新密码',
        're_password.confirm'  => '两次输入的密码不一致',
        'admin_name.require'   => '请输入用户名',
        'admin_name.max'       => '用户名不超过50个字符',
        'group_id.require'     => '请选择权限组',
        'group_id.integer'     => '权限组参数错误',
    ];

    protected array $scene = [
        'login'          => ['username', 'password'],
        'updatePassword' => ['old_password', 'new_password', 're_password'],
        'create'         => ['admin_name', 'admin_password', 'group_id'],
        'edit'           => ['admin_name', 'admin_password', 'group_id'],
    ];

    /**
     * 用户名唯一（排除自身：id 来自路由参数，由验证中间件并入）
     */
    protected function checkAdminName($value, $rule, array $data = []): bool|string
    {
        $condition = [['admin_name', '=', $value]];
        $excludeId = $data['admin_id'] ?? $data['id'] ?? null;
        if ($excludeId) {
            $condition[] = ['admin_id', '<>', $excludeId];
        }
        return \app\model\Admin::where($condition)->exists() ? '用户名已存在' : true;
    }

    /**
     * 自改密码：长度与强度走 PasswordPolicy（配置中心「安全」组）
     */
    protected function checkNewPassword($value, $rule, array $data = []): bool|string
    {
        return \app\service\admin\PasswordPolicy::validate((string) $value) ?: true;
    }

    /**
     * 密码：新建必填；长度与强度走 PasswordPolicy（配置中心「安全」组），编辑留空表示不修改
     */
    protected function checkAdminPassword($value, $rule, array $data = []): bool|string
    {
        if ($value === null || $value === '') {
            return $this->currentScene == 'create' ? '请输入密码' : true;
        }
        return \app\service\admin\PasswordPolicy::validate((string) $value) ?: true;
    }
}
