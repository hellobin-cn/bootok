<?php

namespace app\service\wechat;

use app\service\ConfigService;
use support\Redis;
use Throwable;

/**
 * 微信基类：access_token 管理（stable_token 不互相顶掉，Redis 缓存，凭据失效可强刷）
 * 配置来自配置中心「公众号」组（wechat_appid/wechat_appsecret/wechat_token）
 */
abstract class WechatService
{
    protected string $appid = '';
    protected string $secret = '';

    protected string $access_token_cache_key = 'wechat:access-token';

    public function __construct()
    {
        $config = ConfigService::group('公众号');
        $this->appid = (string) ($config['wechat_appid'] ?? '');
        $this->secret = (string) ($config['wechat_appsecret'] ?? '');
        if ($this->appid !== '') {
            $this->access_token_cache_key .= '-' . $this->appid;
        }
    }

    public function getAccessToken(): string
    {
        $token = Redis::connection()->get($this->access_token_cache_key);
        if ($token) {
            return (string) $token;
        }
        [$token, $error] = $this->fetchAccessToken();
        if ($token !== '') {
            // 有效期 7200s，留 200s 余量
            Redis::connection()->setex($this->access_token_cache_key, 7000, $token);
        } else {
            \support\Log::error('获取微信 AccessToken 失败', $error ?? []);
        }
        return $token;
    }

    /** 凭据失效（40001/40014/42001）时强制刷新重试 */
    public function refreshAccessToken(): string
    {
        Redis::connection()->del($this->access_token_cache_key);
        return $this->getAccessToken();
    }

    /** @return array{0: string, 1: array|null} [token, 失败错误上下文] */
    private function fetchAccessToken(): array
    {
        if ($this->appid === '' || $this->secret === '') {
            return ['', ['errmsg' => '公众号配置不完整（参数设置-公众号）']];
        }
        $res = \BootOk\Http::asJson()->timeout(10)->post('https://api.weixin.qq.com/cgi-bin/stable_token', [
            'grant_type' => 'client_credential',
            'appid'      => $this->appid,
            'secret'     => $this->secret,
        ]);
        $data = json_decode((string) $res->body(), true) ?: [];
        return [(string) ($data['access_token'] ?? ''), $data];
    }

    /** 配置是否足以调接口 */
    protected function configured(): bool
    {
        return $this->appid !== '' && $this->secret !== '';
    }

    /** 统一 JSON 接口调用：token 失效自动强刷重试一次 */
    protected function request(string $method, string $path, array $data = []): array
    {
        $attempt = function () use ($method, $path, $data) {
            $res = \BootOk\Http::asJson()->timeout(20)->{$method}(
                'https://api.weixin.qq.com' . $path . (str_contains($path, '?') ? '&' : '?') . 'access_token=' . $this->getAccessToken(),
                $data
            );
            return json_decode((string) $res->body(), true) ?: [];
        };
        $body = $attempt();
        if (in_array((int) ($body['errcode'] ?? 0), [40001, 40014, 42001], true)) {
            $this->refreshAccessToken();
            $body = $attempt();
        }
        return $body;
    }

    /** 上传类 multipart 接口调用（同 token 重试语义） */
    protected function upload(string $path, string $field, string $bytes, string $filename, array $query = []): array
    {
        $attempt = function () use ($path, $field, $bytes, $filename, $query) {
            $url = 'https://api.weixin.qq.com' . $path . (str_contains($path, '?') ? '&' : '?') . 'access_token=' . $this->getAccessToken();
            foreach ($query as $k => $v) {
                $url .= '&' . $k . '=' . urlencode((string) $v);
            }
            $res = \BootOk\Http::asMultipart($field, $bytes, $filename)->timeout(60)->post($url);
            return json_decode((string) $res->body(), true) ?: [];
        };
        $body = $attempt();
        if (in_array((int) ($body['errcode'] ?? 0), [40001, 40014, 42001], true)) {
            $this->refreshAccessToken();
            $body = $attempt();
        }
        return $body;
    }

    /** 下载远程图片字节（正文图转存用），失败返回 null */
    protected function download(string $url): ?string
    {
        try {
            $res = \BootOk\Http::timeout(20)->get($url);
            if (!$res->ok()) {
                return null;
            }
            $body = (string) $res->body();
            return $body !== '' ? $body : null;
        } catch (Throwable) {
            return null;
        }
    }
}
