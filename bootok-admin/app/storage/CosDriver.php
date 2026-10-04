<?php

namespace app\storage;

use BootOk\Http;
use BootOk\Http\ConnectionException;
use RuntimeException;
use Webman\File;

/**
 * 腾讯云 COS 驱动（纯 PHP 签名，无 SDK）
 *
 * 签名：q-sign-algorithm=sha1 四步链（文档：https://cloud.tencent.com/document/product/436/7778），
 * HttpString 规范化经官方文档示例值逐字核对（含中文路径与 params 编码）。
 * 渠道配置 config：
 *   bucket     存储桶全名，含 APPID 后缀，如 mybucket-1250000000
 *   region     地域，如 ap-beijing
 *   secret_id  腾讯云 SecretId
 *   secret_key 腾讯云 SecretKey
 *   cdn_domain 可选，绑定 CDN 域名后 URL 走 CDN
 *   scheme     CDN/URL 访问协议 http/https（默认 https）
 */
class CosDriver implements StorageInterface
{
    private string $bucket;

    private string $region;

    private string $secretId;

    private string $secretKey;

    private ?string $cdnDomain;

    private string $scheme;

    public function __construct(array $config)
    {
        $this->bucket = (string) ($config['bucket'] ?? '');
        $this->region = (string) ($config['region'] ?? '');
        $this->secretId = (string) ($config['secret_id'] ?? '');
        $this->secretKey = (string) ($config['secret_key'] ?? '');
        $this->cdnDomain = trim((string) ($config['cdn_domain'] ?? '')) ?: null;
        $this->scheme = ($config['scheme'] ?? 'https') === 'http' ? 'http' : 'https';
        if ($this->bucket === '' || $this->region === '' || $this->secretId === '' || $this->secretKey === '') {
            throw new \InvalidArgumentException('COS 渠道需要 bucket / region / secret_id / secret_key');
        }
    }

    public function put(File $file, string $relativePath): void
    {
        $this->request('PUT', $relativePath, body: (string) $file->getContent());
    }

    public function putContent(string $relativePath, string $content): void
    {
        $this->request('PUT', $relativePath, body: $content);
    }

    public function get(string $relativePath): ?string
    {
        try {
            return $this->request('GET', $relativePath);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), '404')) {
                return null;
            }
            throw $e;
        }
    }

    public function exists(string $relativePath): bool
    {
        try {
            $this->request('HEAD', $relativePath);

            return true;
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), '404')) {
                return false;
            }
            throw $e;
        }
    }

    public function delete(string $relativePath): void
    {
        // 对象不存在时 COS 返回 404，同样视为删除成功
        try {
            $this->request('DELETE', $relativePath);
        } catch (RuntimeException $e) {
            if (!str_contains($e->getMessage(), '404')) {
                throw $e;
            }
        }
    }

    public function url(string $relativePath): string
    {
        if ($this->cdnDomain !== null) {
            return $this->scheme() . '://' . rtrim($this->cdnDomain, '/') . '/' . $this->encodePath($relativePath);
        }

        return $this->scheme() . '://' . $this->host() . '/' . $this->encodePath($relativePath);
    }

    /**
     * 签名 URL（下载场景：PUT/GET 均可签名）
     */
    public function signedUrl(string $relativePath, int $expires = 3600): string
    {
        $start = time();
        $end = $start + $expires;
        $keyTime = "{$start};{$end}";
        $pathname = '/' . $this->encodePath($relativePath);

        [$signature] = $this->sign('get', $pathname, '', '', $keyTime);
        $query = http_build_query([
            'q-sign-algorithm' => 'sha1',
            'q-ak'             => $this->secretId,
            'q-sign-time'      => $keyTime,
            'q-key-time'       => $keyTime,
            'q-header-list'    => '',
            'q-url-param-list' => '',
            'q-signature'      => $signature,
        ]);

        return $this->scheme() . '://' . $this->host() . $pathname . '?' . $query;
    }

    public function test(): array
    {
        $probe = 'bootok-probe/' . date('Ymd') . '_' . bin2hex(random_bytes(4)) . '.tmp';
        $content = 'bootok-storage-probe ' . date('Y-m-d H:i:s');
        $start = microtime(true);
        try {
            $this->putContent($probe, $content);
            $read = $this->get($probe);
            if ($read !== $content) {
                return ['ok' => false, 'message' => '写入后读取内容不一致（签名或网络异常）'];
            }
            $this->delete($probe);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }
        $elapsed = round((microtime(true) - $start) * 1000);

        return ['ok' => true, 'message' => "COS 读写删全通（{$elapsed}ms）：{$this->host()}"];
    }

    /**
     * 签名并执行请求（经 bootok/http HTTP 层）
     *
     * @return string GET/HEAD 的响应体（其他动词为空串）
     */
    private function request(string $verb, string $relativePath, string $body = ''): string
    {
        $pathname = '/' . $this->encodePath($relativePath);
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $host = $this->host();
        // COS 规范：HttpString 的 header 值必须 URL 编码（漏编码 content-type 的 / 曾致 SignatureDoesNotMatch）
        $headers = $verb === 'PUT'
            ? 'content-length=' . rawurlencode((string) strlen($body))
                . '&content-type=' . rawurlencode('application/octet-stream')
                . '&date=' . rawurlencode($date)
                . '&host=' . rawurlencode($host)
            : 'date=' . rawurlencode($date) . '&host=' . rawurlencode($host);
        $keyTime = time() - 60 . ';' . (time() + 600);

        [$signature, $signKey] = $this->sign(strtolower($verb), $pathname, '', $headers, $keyTime);
        $authorization = 'q-sign-algorithm=sha1&q-ak=' . $this->secretId
            . '&q-sign-time=' . $keyTime
            . '&q-key-time=' . $keyTime
            . '&q-header-list=' . ($verb === 'PUT' ? 'content-length;content-type;date;host' : 'date;host')
            . '&q-url-param-list=&q-signature=' . $signature;

        $httpHeaders = [
            'Authorization' => $authorization,
            'Date'          => $date,
            'Host'          => $host,
        ];

        try {
            if ($verb === 'PUT') {
                // withBody 自带 Content-Type 头；content-length 由 HTTP 层按 body 计算
                $resp = Http::withHeaders($httpHeaders)->timeout(30)
                    ->withBody($body, 'application/octet-stream')->put($this->scheme() . '://' . $host . $pathname);
            } else {
                // removeBodyFormat：杜绝 DELETE/GET 被补 body 与隐式 Content-Type（签名头列表之外的隐式头虽不参与 COS 校验，保持请求最小化）
                $http = Http::withHeaders($httpHeaders)->timeout(30)->removeBodyFormat();
                $resp = match ($verb) {
                    'HEAD'   => $http->head($this->scheme() . '://' . $host . $pathname),
                    'DELETE' => $http->delete($this->scheme() . '://' . $host . $pathname),
                    default  => $http->get($this->scheme() . '://' . $host . $pathname),
                };
            }
        } catch (ConnectionException $e) {
            throw new RuntimeException("COS {$verb} {$relativePath} 传输失败：" . $e->getMessage());
        }

        $status = $resp->status();
        if ($status >= 400) {
            $detail = mb_substr(strip_tags($resp->body()), 0, 900);
            throw new RuntimeException("COS {$verb} {$relativePath} 失败：HTTP {$status} {$detail}");
        }

        return $verb === 'GET' ? $resp->body() : '';
    }

    /**
     * sha1 签名链四步：SignKey → HttpString → StringToSign → Signature
     *
     * @return array{0: string, 1: string} [Signature, SignKey]
     */
    private function sign(string $methodLower, string $pathname, string $params, string $headers, string $keyTime): array
    {
        // Step1: SignKey = HMAC-SHA1(KeyTime, SecretKey)
        $signKey = hash_hmac('sha1', $keyTime, $this->secretKey);
        // Step2: HttpString = 方法小写\nURI\n参数\n头部\n
        $httpString = strtolower($methodLower) . "\n" . $pathname . "\n" . $params . "\n" . $headers . "\n";
        // Step3: StringToSign = sha1\nKeyTime\nsha1(HttpString)\n
        $stringToSign = 'sha1' . "\n" . $keyTime . "\n" . sha1($httpString) . "\n";
        // Step4: Signature = HMAC-SHA1(StringToSign, SignKey)
        $signature = hash_hmac('sha1', $stringToSign, $signKey);

        return [$signature, $signKey];
    }

    private function host(): string
    {
        return $this->bucket . '.cos.' . $this->region . '.myqcloud.com';
    }

    /**
     * COS 对象键按段 URL 编码（保留 /）
     */
    private function encodePath(string $relativePath): string
    {
        return implode('/', array_map('rawurlencode', explode('/', ltrim($relativePath, '/'))));
    }

    private function scheme(): string
    {
        return $this->scheme;
    }
}
