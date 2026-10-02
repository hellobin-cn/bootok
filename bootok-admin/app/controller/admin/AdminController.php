<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\enum\Code;
use app\model\Admin;
use app\service\admin\AdminService;
use app\service\admin\AdminTokenService;
use DI\Attribute\Inject;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use support\Request;
use support\Response;

class AdminController extends Controller
{
    #[Inject]
    private ?AdminService $adminService = null;

    public function login(Request $request): Response
    {
        return $this->uniResponse($this->adminService->login($request->post('username'), $request->post('password')));
    }

    public function logout(): Response
    {
        return $this->uniResponse($this->adminService->logout($this->adminId));
    }

    public function updatePassword(Request $request): Response
    {
        return $this->uniResponse($this->adminService->updatePassword(
            $this->adminId,
            $request->post('old_password'),
            $request->post('new_password'),
            $request->post('re_password')
        ));
    }

    /**
     * 在线会话列表（仅超管）：基于 admin 渠道的 Redis 单点记录
     */
    public function online(): Response
    {
        if (!$this->guardSuper()) {
            return $this->error(Code::FORBIDDEN->getDescription(), Code::FORBIDDEN->value);
        }
        $prefix = config('app.name') . ':admin-u:';
        $list = [];
        foreach ((array)\support\Redis::keys($prefix . '*') as $key) {
            $adminId = (int)substr($key, strlen($prefix));
            if ($adminId <= 0) {
                continue;
            }
            $admin = Admin::find($adminId);
            $loginTime = '';
            try {
                $token = (string)\support\Redis::get($key);
                $claims = JWT::decode($token, new Key((string)config('token.channels.admin.key'), (string)config('token.alg')));
                $loginTime = date('Y-m-d H:i:s', $claims->iat ?? time());
            } catch (\Throwable) {
            }
            $list[] = [
                'admin_id'   => $adminId,
                'admin_name' => $admin?->admin_name ?? "用户{$adminId}",
                'is_super'   => $admin?->is_super ?? 0,
                'login_time' => $loginTime,
            ];
        }
        return $this->success($list);
    }

    /**
     * 强制下线（仅超管）：不能踢自己；内置超管（admin_id=1）不可被踢
     */
    public function kick(int $adminId): Response
    {
        if (!$this->guardSuper()) {
            return $this->error(Code::FORBIDDEN->getDescription(), Code::FORBIDDEN->value);
        }
        if ($adminId == $this->adminId) {
            return $this->error('不能强制下线自己');
        }
        if ($adminId == 1) {
            return $this->error('内置超级管理员不可被下线');
        }
        return $this->uniResponse($this->adminService->logout($adminId));
    }

    private function guardSuper(): bool
    {
        return $this->isSuper == 1 || $this->adminId == 1;
    }
}
