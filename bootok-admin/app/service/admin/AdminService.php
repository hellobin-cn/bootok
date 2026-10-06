<?php

namespace app\service\admin;

use app\entity\ServiceResult;
use app\enum\Code;
use app\model\Admin;
use app\model\LoginLog;
use app\service\MessageService;
use app\service\Service;
use DI\Attribute\Inject;use support\Log;
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
            $fails = (int) Redis::incr($failKey);
            Redis::expire($failKey, self::FAIL_WINDOW);
            // 达到阈值的临界一次才告警，窗口内的后续尝试不重复通知
            if ($fails === self::FAIL_LIMIT) {
                (new MessageService())->sendToSupers(
                    "登录防爆破已锁定：{$username}",
                    'IP ' . request()->getRealIp() . " 连续 {$fails} 次登录失败，该 IP+用户名 组合已被锁定 10 分钟。",
                    MessageService::TYPE_SECURITY
                );
            }
            $this->recordLogin(0, (string)$username, false, '用户名或密码错误');
            $res->code = Code::FAIL->value;
            $res->message = '用户名或密码错误';
            return $res;
        }
        Redis::del($failKey);
        // 两步验证已启用：密码通过只发一次性票券，验证动态码后才发 token
        if ((int) $admin->totp_enabled === 1 && (string) $admin->totp_secret !== '') {
            $ticket = bin2hex(random_bytes(16));
            Redis::setex('login:2fa:' . $ticket, 300, (string) $admin->admin_id);
            $this->recordLogin($admin->admin_id, (string) $username, true, '密码通过，等待两步验证');
            $res->data = ['need_2fa' => true, 'ticket' => $ticket, 'expires_in' => 300];
            return $res;
        }
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

    /**
     * 两步验证登录：凭一次性票券 + 动态码换正式 token
     * 票券 5 分钟有效，错误 3 次作废
     */
    public function verify2fa(string $ticket, string $code): ServiceResult
    {
        $res = new ServiceResult();
        $ticket = trim($ticket);
        $key = 'login:2fa:' . $ticket;
        $adminId = (int) Redis::get($key);
        if ($adminId <= 0) {
            $res->code = Code::FAIL->value;
            $res->message = '验证已过期，请重新登录';
            return $res;
        }
        $failKey = 'login:2fa:fail:' . $ticket;
        if ((int) Redis::get($failKey) >= 3) {
            Redis::del($key);
            $res->code = Code::FAIL->value;
            $res->message = '动态码错误次数过多，请重新登录';
            return $res;
        }
        $admin = Admin::find($adminId);
        if ($admin === null || !TotpService::verify((string) $admin->totp_secret, $code)) {
            Redis::incr($failKey);
            Redis::expire($failKey, 300);
            $this->recordLogin($adminId, (string) ($admin->admin_name ?? ''), false, '两步验证动态码错误');
            $res->code = Code::FAIL->value;
            $res->message = '动态码不正确';
            return $res;
        }
        Redis::del($key);
        Redis::del($failKey);
        $this->recordLogin($admin->admin_id, $admin->admin_name, true, '');
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

    /** 生成绑定密钥（未验证前不生效，totp_enabled 保持 0） */
    public function totpSetup(int $adminId): ServiceResult
    {
        $res = new ServiceResult();
        $admin = Admin::find($adminId);
        if ($admin === null) {
            $res->code = Code::FAIL->value;
            $res->message = '管理员不存在';
            return $res;
        }
        $secret = TotpService::generateSecret();
        $admin->totp_secret = $secret;
        $admin->totp_enabled = 0;
        $admin->save();
        $res->data = ['secret' => $secret, 'uri' => TotpService::otpauthUri($secret, $admin->admin_name)];
        return $res;
    }

    /** 用动态码确认绑定 */
    public function totpEnable(int $adminId, string $code): ServiceResult
    {
        $res = new ServiceResult();
        $admin = Admin::find($adminId);
        if ($admin === null || (string) $admin->totp_secret === '') {
            $res->code = Code::FAIL->value;
            $res->message = '请先生成绑定密钥';
            return $res;
        }
        if ((int) $admin->totp_enabled === 1) {
            $res->code = Code::FAIL->value;
            $res->message = '两步验证已开启';
            return $res;
        }
        if (!TotpService::verify((string) $admin->totp_secret, $code)) {
            $res->code = Code::FAIL->value;
            $res->message = '动态码不正确，请重试';
            return $res;
        }
        $admin->totp_enabled = 1;
        $admin->save();
        $res->data = ['enabled' => true];
        return $res;
    }

    /** 解绑：登录密码 + 动态码双确认 */
    public function totpDisable(int $adminId, string $password, string $code): ServiceResult
    {
        $res = new ServiceResult();
        $admin = Admin::find($adminId);
        if ($admin === null || (int) $admin->totp_enabled !== 1) {
            $res->code = Code::FAIL->value;
            $res->message = '两步验证未开启';
            return $res;
        }
        if (!verify_password($password, $admin->admin_password)) {
            $res->code = Code::FAIL->value;
            $res->message = '登录密码不正确';
            return $res;
        }
        if (!TotpService::verify((string) $admin->totp_secret, $code)) {
            $res->code = Code::FAIL->value;
            $res->message = '动态码不正确';
            return $res;
        }
        $admin->totp_enabled = 0;
        $admin->totp_secret = '';
        $admin->save();
        $res->data = ['enabled' => false];
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
