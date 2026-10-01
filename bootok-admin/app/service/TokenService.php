<?php

namespace app\service;

use DI\Attribute\Inject;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Ramsey\Uuid\Uuid;
use support\Redis;

class TokenService
{
    #[Inject("jwt_iss")]
    private string $iss = '';
    #[Inject("jwt_aud")]
    private string $aud = '';
    #[Inject("jwt_key")]
    private string $key = '';
    #[Inject("jwt_leeway")]
    private int $leeway = 0;
    #[Inject("access_token_exp")]
    private int $accessTokenExp = 0;
    #[Inject("refresh_token_exp")]
    private int $refreshTokenExp = 0;
    /** 剩余有效期低于该秒数时在响应头下发新token（滑动续期），0为关闭 */
    #[Inject("token_renew_threshold")]
    private int $renewThreshold = 0;

    /** 换发后旧token的宽限时长（秒），覆盖续期瞬间的并发请求 */
    private const GRACE_SECONDS = 60;

    private string $alg = 'HS256';

    private function getUserKey($userId): string
    {
        return config('app.name') . ':u:' . $userId;
    }

    private function getGraceKey($userId): string
    {
        return config('app.name') . ':u-prev:' . $userId;
    }

    /**
     * 创建token，$payload 附加到JWT的data声明中（如 adminName/groupId/isSuper）
     */
    public function create($userId, array $payload = [], string $tokenType = 'access'): string
    {
        $currentTime = time();
        $claims = [
            'iss' => $this->iss,
            'aud' => $this->aud,
            'exp' => $currentTime + ($tokenType == 'refresh' ? $this->refreshTokenExp : $this->accessTokenExp),
            'iat' => $currentTime,
            'nbf' => $currentTime,
            'jti' => Uuid::uuid4()->toString(),
            'sub' => $userId,
            'typ' => $tokenType,
            'data' => $payload,
        ];
        $token = JWT::encode($claims, $this->key, $this->alg);
        if ($tokenType == 'access') {
            $key = $this->getUserKey($userId);
            if ($this->accessTokenExp) {
                Redis::setEx($key, $this->accessTokenExp, $token);
            } else {
                Redis::set($key, $token);
            }
        }
        return $token;
    }

    public function verify(string $token, &$data): bool
    {
        JWT::$leeway = $this->leeway;
        try {
            $data = JWT::decode($token, new Key($this->key, $this->alg));
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
        if ($this->renewThreshold <= 0) {
            return null;
        }
        $remain = ($claims->exp ?? 0) - time();
        if ($remain > $this->renewThreshold) {
            return null;
        }
        // 进入宽限的是被顶掉的main（可能是并发展开的另一代token），而非本次请求携带的旧token
        $displaced = Redis::get($this->getUserKey($claims->sub));
        $newToken = $this->create($claims->sub, (array)($claims->data ?? []));
        if ($displaced) {
            Redis::sAdd($this->getGraceKey($claims->sub), $displaced);
            Redis::expire($this->getGraceKey($claims->sub), self::GRACE_SECONDS);
        }
        return $newToken;
    }

    public function delete($userId): bool
    {
        Redis::del($this->getGraceKey($userId));
        return (bool)Redis::del($this->getUserKey($userId));
    }
}
