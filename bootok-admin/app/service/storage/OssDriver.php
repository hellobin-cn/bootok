<?php

namespace app\service\storage;

use BootOk\Http;
use BootOk\Http\ConnectionException;
use RuntimeException;
use Webman\File;

/**
 * 阿里云 OSS 驱动（纯 PHP 签名，无 SDK）
 *
 * 签名：OSS Header 签名 V1（HMAC-SHA1，文档：https://www.alibabacloud.com/help/zh/oss/developer-reference/include-the-v1-signature-in-the-authorization-header）
 * 渠道配置 config：
 *   bucket            存储桶
 *   endpoint          地域端点，如 oss-cn-beijing.aliyuncs.com（可填内网 endpoint）
 *   access_key_id     AccessKey ID
 *   access_key_secret AccessKey Secret
 *   cdn_domain        可选，绑定 CDN 域名后 URL 走 CDN
 *   scheme            CDN/URL 访问协议 http/https（默认 https）
 */
class OssDriver implements StorageInterface
{
    private string $bucket;

    private string $endpoint;

    private string $accessKeyId;

    private string $accessKeySecret;

    private ?string $cdnDomain;

    private string $scheme;

    public function __construct(array $config)
    {
        $this->bucket = (string) ($config['bucket'] ?? '');
        $this->endpoint = preg_replace('#^https?://#', '', (string) ($config['endpoint'] ?? ''));
        $this->accessKeyId = (string) ($config['access_key_id'] ?? '');
        $this->accessKeySecret = (string) ($config['access_key_secret'] ?? '');
        $this->cdnDomain = trim((string) ($config['cdn_domain'] ?? '')) ?: null;
        $this->scheme = ($config['scheme'] ?? 'https') === 'http' ? 'http' : 'https';
        if ($this->bucket === '' || $this->endpoint === '' || $this->accessKeyId === '' || $this->accessKeySecret === '') {
            throw new \InvalidArgumentException('OSS 渠道需要 bucket / endpoint / access_key_id / access_key_secret');
        }
    }

    public function put(File $file, string $relativePath): void
    {
        // Webman UploadFile 继承 SplFileInfo，无 getContent；经真实路径读内容
        $this->request('PUT', $relativePath, body: (string) file_get_contents($file->getRealPath()));
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
        return $this->head($relativePath);
    }

    public function delete(string $relativePath): void
    {
        // 对象不存在时 OSS 返回 204，同样视为删除成功
        $this->request('DELETE', $relativePath);
    }

    public function list(string $prefix = ""): array
    {
        $prefix = trim($prefix, '/');
        $queryPrefix = $prefix === '' ? '' : $prefix . '/';
        $result = [];
        $marker = '';
        do {
            $params = ['max-keys' => '1000', 'prefix' => $queryPrefix];
            if ($marker !== '') {
                $params['marker'] = $marker;
            }
            // prefix/marker/max-keys 不属于 V1 签名的子资源白名单：CanonicalizedResource 为 /bucket/，query 只进 URL 不进签名
            ksort($params);
            $urlPairs = [];
            foreach ($params as $k => $v) {
                $urlPairs[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
            }
            $urlQuery = implode('&', $urlPairs);
            $date = gmdate('D, d M Y H:i:s') . ' GMT';
            $stringToSign = "GET\n\n\n{$date}\n/{$this->bucket}/";
            $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));

            $ch = curl_init($this->scheme() . '://' . $this->bucket . '.' . $this->endpoint . '/?' . $urlQuery);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 30,
                CURLOPT_HTTPHEADER     => [
                    'Date: ' . $date,
                    'Authorization: OSS ' . $this->accessKeyId . ':' . $signature,
                ],
            ]);
            $body = curl_exec($ch);
            $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if ($status >= 400 || !is_string($body)) {
                throw new RuntimeException("OSS List 失败：HTTP {$status} " . mb_substr(strip_tags((string) $body) . ' ' . $error, 0, 1500));
            }
            $xml = simplexml_load_string($body);
            foreach ($xml->Contents ?? [] as $content) {
                $key = (string) $content->Key;
                $result[$key] = [
                    'size'  => (int) $content->Size,
                    'mtime' => strtotime((string) $content->LastModified) ?: 0,
                ];
            }
            $truncated = ((string) ($xml->IsTruncated ?? 'false')) === 'true';
            $marker = (string) ($xml->NextMarker ?? '');
        } while ($truncated && $marker !== '');

        // 过滤占位对象
        return array_filter($result, fn (string $key) => !str_ends_with($key, '/.keep'), ARRAY_FILTER_USE_KEY);
    }

    public function copy(string $from, string $to): void
    {
        $source = '/' . $this->bucket . '/' . str_replace('%2F', '/', rawurlencode(ltrim($from, '/')));
        // x-oss-copy-source 的 key 需 URL 编码（保留斜杠），且该头必须参与 V1 签名
        $this->request('PUT', $to, body: '', ossHeaders: ['x-oss-copy-source' => $source]);
    }

    public function deletePrefix(string $prefix): void
    {
        $prefix = trim($prefix, '/');
        foreach (array_keys($this->list($prefix)) as $key) {
            $this->request('DELETE', $key);
        }
    }

    public function url(string $relativePath): string
    {
        if ($this->cdnDomain !== null) {
            return $this->scheme() . '://' . rtrim($this->cdnDomain, '/') . '/' . ltrim($relativePath, '/');
        }

        return $this->scheme() . '://' . $this->bucket . '.' . $this->endpoint . '/' . ltrim($relativePath, '/');
    }

    public function signedUrl(string $relativePath, int $expires = 3600): string
    {
        $path = '/' . ltrim($relativePath, '/');
        $expiresAt = time() + $expires;
        $stringToSign = "GET\n\n\n{$expiresAt}\n/{$this->bucket}{$path}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));

        return $this->baseUrl($path) . '?OSSAccessKeyId=' . rawurlencode($this->accessKeyId)
            . '&Expires=' . $expiresAt
            . '&Signature=' . rawurlencode($signature);
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

        return ['ok' => true, 'message' => "OSS 读写删全通（{$elapsed}ms）：{$this->bucket}.{$this->endpoint}"];
    }

    private function head(string $relativePath): bool
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

    /**
     * 签名请求并执行（经 bootok/http HTTP 层）
     *
     * @param array<string, string> $ossHeaders 参与 V1 签名 CanonicalizedOSSHeaders 的 x-oss-* 头（如 copy 的 x-oss-copy-source）
     * @return string GET/HEAD 的响应体（其他动词为空串）
     */
    private function request(string $verb, string $relativePath, string $body = '', array $ossHeaders = []): string
    {
        // 签名用原始 key（未编码）；URL 用编码后 path（中文 key 场景与 list 前缀过滤保持同一形态）
        $path = '/' . ltrim($relativePath, '/');
        $encodedPath = implode('/', array_map('rawurlencode', explode('/', $path)));
        $contentMd5 = $body !== '' && $verb === 'PUT' ? base64_encode(md5($body, true)) : '';
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $contentType = $verb === 'PUT' ? 'application/octet-stream' : '';

        // CanonicalizedOSSHeaders：x-oss-* 头名小写排序后逐行 name:value\n，参与签名计算
        $canonical = [];
        foreach ($ossHeaders as $name => $value) {
            $canonical[strtolower((string) $name)] = (string) $value;
        }
        ksort($canonical);
        $canonicalOssHeaders = '';
        foreach ($canonical as $name => $value) {
            $canonicalOssHeaders .= $name . ':' . $value . "\n";
        }

        $stringToSign = $verb . "\n"
            . $contentMd5 . "\n"
            . $contentType . "\n"
            . $date . "\n"
            . $canonicalOssHeaders
            . "/{$this->bucket}{$path}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));

        $headers = [
            'Date'         => $date,
            'Authorization' => 'OSS ' . $this->accessKeyId . ':' . $signature,
        ];
        foreach ($canonical as $name => $value) {
            $headers[$name] = $value;
        }
        if ($verb === 'PUT') {
            $headers['Content-MD5'] = $contentMd5;
        }

        try {
            if ($verb === 'PUT') {
                // withBody 自带 Content-Type 头（参与签名，勿在 withHeaders 重复声明）
                $resp = Http::withHeaders($headers)->timeout(30)
                    ->withBody($body, $contentType)->put($this->baseUrl($encodedPath));
            } else {
                // removeBodyFormat：杜绝 DELETE/GET 被补 body 与隐式 Content-Type（参与签名计算）
                $http = Http::withHeaders($headers)->timeout(30)->removeBodyFormat();
                $resp = match ($verb) {
                    'HEAD'   => $http->head($this->baseUrl($encodedPath)),
                    'DELETE' => $http->delete($this->baseUrl($encodedPath)),
                    default  => $http->get($this->baseUrl($encodedPath)),
                };
            }
        } catch (ConnectionException $e) {
            throw new RuntimeException("OSS {$verb} {$relativePath} 传输失败：" . $e->getMessage());
        }

        $status = $resp->status();
        if ($status >= 400) {
            $detail = mb_substr(strip_tags($resp->body()), 0, 900);
            throw new RuntimeException("OSS {$verb} {$relativePath} 失败：HTTP {$status} {$detail}");
        }

        return $verb === 'GET' ? $resp->body() : '';
    }

    private function baseUrl(string $path): string
    {
        return $this->scheme() . '://' . $this->bucket . '.' . $this->endpoint . $path;
    }

    private function scheme(): string
    {
        // 内网 endpoint 常要求内网协议，公网访问统一走配置 scheme
        return $this->scheme;
    }
}
