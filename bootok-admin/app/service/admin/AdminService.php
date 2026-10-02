<?php

namespace app\service\admin;

use app\entity\ServiceResult;
use app\enum\Code;
use app\model\Admin;
use app\model\LoginLog;
use app\service\Service;
use DI\Attribute\Inject;
use support\Log;
use support\Redis;

class AdminService extends Service
{
    #[Inject]
    private ?AdminTokenService $tokenService = null;

    /** 登录失败限制：窗口期内达到上限后锁定 */
    private const FAIL_LIMIT = 5;
    private const FAIL_WINDOW = 600;

    public function login($username, $password): ServiceResult
    {
        $res = new ServiceResult();
        if ($username === null || $password === null) {
            $res->code = Code::PARAM_VALIDATE->value;
            $res->message = '请输入用户名和密码';
            return $res;
        }
        // 防爆破：按 IP+用户名 计数，窗口期内达到上限即锁定
        $failKey = 'login:fail:' . md5(request()->getRealIp() . '|' . $username);
        if ((int)Redis::get($failKey) >= self::FAIL_LIMIT) {
            $this->recordLogin(0, (string)$username, false, '失败次数过多');
            $res->code = Code::FAIL->value;
            $res->message = '失败次数过多，请10分钟后再试';
            return $res;
        }
        $admin = Admin::where('admin_name', $username)->first();
        if ($admin == null || !verify_password($password, $admin->admin_password)) {
            Redis::incr($failKey);
            Redis::expire($failKey, self::FAIL_WINDOW);
            $this->recordLogin(0, (string)$username, false, '用户名或密码错误');
            $res->code = Code::FAIL->value;
            $res->message = '用户名或密码错误';
            return $res;
        }
        Redis::del($failKey);
        $this->recordLogin($admin->admin_id, (string)$username, true, '');
        $res->data = [
            'access_token'     => $this->tokenService->create($admin->admin_id, [
                'adminName' => $admin->admin_name,
                'groupId'   => $admin->group_id,
                'isSuper'   => $admin->is_super,
            ]),
            'access_token_exp' => config('token.channels.admin.exp'),
        ];
        return $res;
    }

    public function logout($adminId): ServiceResult
    {
        $res = new ServiceResult();
        $res->data = [];
        $res->message = $this->tokenService->delete($adminId) ? '退出成功' : '退出失败';
        if ($res->message == '退出失败') {
            $res->code = Code::FAIL->value;
        }
        return $res;
    }

    public function updatePassword($adminId, $oldPassword, $newPassword, $rePassword): ServiceResult
    {
        $res = new ServiceResult();
        $admin = Admin::find($adminId);
        if (!$admin || !verify_password($oldPassword ?? '', $admin->admin_password)) {
            $res->code = Code::FAIL->value;
            $res->message = '原密码错误';
            return $res;
        }
        if (strlen($newPassword ?? '') < 6) {
            $res->code = Code::PARAM_VALIDATE->value;
            $res->message = '新密码不能少于6位';
            return $res;
        }
        if ($newPassword !== $rePassword) {
            $res->code = Code::PARAM_VALIDATE->value;
            $res->message = '两次输入的密码不一致';
            return $res;
        }
        $admin->admin_password = $newPassword;
        $res->message = $admin->save() ? '密码已更新' : '密码更新失败';
        if ($res->message == '密码更新失败') {
            $res->code = Code::FAIL->value;
        }
        return $res;
    }

    /**
     * 登录日志（成功与失败均记录，写失败不影响登录）
     */
    private function recordLogin(int $adminId, string $adminName, bool $ok, string $message): void
    {
        try {
            LoginLog::create([
                'admin_id'   => $adminId,
                'admin_name' => $adminName,
                'status'     => $ok ? 1 : 0,
                'ip'         => request()->getRealIp(),
                'user_agent' => mb_substr((string)request()->header('user-agent', ''), 0, 255),
                'message'    => $message,
            ]);
        } catch (\Throwable $e) {
            Log::error('登录日志写入失败: ' . $e->getMessage());
        }
    }
}
