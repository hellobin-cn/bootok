<?php

namespace app\service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Ramsey\Uuid\Uuid;
use support\Redis;

/**
 * Token 全局基类（渠道化）
 * 签发/校验/滑动续期/单点管理的全部逻辑在此；渠道差异（键前缀、密钥、有效期）
 * 通过重写 channel() + config/token.php 渠道段实现，子类零逻辑
 * 例如：app\service\admin\AdminTokenService（admin 渠道）
 */
class TokenService
{
    /** 渠道名，对应 config/token.php channels 键；子类重写 */
    protected function channel(): string
    {
        return 'default';
    }

    protected function conf(string $key, mixed $default = null): mixed
    {
        return config("token.channels.{$this->channel()}.{$key}", $default);
    }

    protected function keyPrefix(): string
    {
        return (string)$this->conf('prefix', 'u');
    }

    protected function jwtKey(): string
    {
        return (string)$this->conf('key');
    }

    protected function accessTokenExp(): int
    {
        return (int)$this->conf('exp', 7200);
    }

    protected function renewThreshold(): int
    {
        return (int)$this->conf('renew_threshold', 0);
    }

    protected function graceSeconds(): int
    {
        return (int)config('token.renew_grace', 60);
    }

    private function getUserKey($userId): string
    {
        return config('app.name') . ':' . $this->keyPrefix() . ':' . $userId;
    }

    private function getGraceKey($userId): string
    {
        return config('app.name') . ':' . $this->keyPrefix() . '-prev:' . $userId;
    }

    /**
     * 创建token，$payload 附加到JWT的data声明中（如 adminName/groupId/isSuper）
     */
    public function create($userId, array $payload = [], string $tokenType = 'access'): string
    {
        $currentTime = time();
        $claims = [
            'iss'  => (string)$this->conf('iss', ''),
            'aud'  => (string)$this->conf('aud', ''),
            'exp'  => $currentTime + $this->accessTokenExp(),
            'iat'  => $currentTime,
            'nbf'  => $currentTime,
            'jti'  => Uuid::uuid4()->toString(),
            'sub'  => $userId,
            'typ'  => $tokenType,
            'data' => $payload,
        ];
        $token = JWT::encode($claims, $this->jwtKey(), config('token.alg', 'HS256'));
        if ($tokenType == 'access') {
            $key = $this->getUserKey($userId);
            if ($this->accessTokenExp()) {
                Redis::setEx($key, $this->accessTokenExp(), $token);
            } else {
                Redis::set($key, $token);
            }
        }
        return $token;
    }

    public function verify(string $token, &$data): bool
    {
        JWT::$leeway = (int)config('token.leeway', 0);
        try {
            $data = JWT::decode($token, new Key($this->jwtKey(), config('token.alg', 'HS256')));
            // 服务端记录的token须与客户端传递的一致（后登录踢掉先登录）；
            // 宽限集合接受近期被换发的token，覆盖滑动续期瞬间的并发请求
            if (Redis::get($this->getUserKey($data->sub)) != $token
                && !Redis::sIsMember($this->getGraceKey($data->sub), $token)) {
                return false;
            }
        } catch (\Exception) {
            return false;
        }
        return true;
    }

    /**
     * 滑动续期：token 剩余有效期低于阈值时签发新token并替换单点记录，
     * 被顶掉的旧token进入宽限集合。返回新token；未到阈值返回 null（无需续期）
     */
    public function renew(string $oldToken, object $claims): ?string
    {
        $threshold = $this->renewThreshold();
        if ($threshold <= 0) {
            return null;
        }
        $remain = ($claims->exp ?? 0) - time();
        if ($remain > $threshold) {
            return null;
        }
        // 进入宽限的是被顶掉的main（可能是并发展开的另一代token），而非本次请求携带的旧token
        $displaced = Redis::get($this->getUserKey($claims->sub));
        $newToken = $this->create($claims->sub, (array)($claims->data ?? []));
        if ($displaced) {
            Redis::sAdd($this->getGraceKey($claims->sub), $displaced);
            Redis::expire($this->getGraceKey($claims->sub), $this->graceSeconds());
        }
        return $newToken;
    }

    public function delete($userId): bool
    {
        Redis::del($this->getGraceKey($userId));
        return (bool)Redis::del($this->getUserKey($userId));
    }
}
