<?php

namespace app\storage;

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
        return $this->head($relativePath);
    }

    public function delete(string $relativePath): void
    {
        // 对象不存在时 OSS 返回 204，同样视为删除成功
        $this->request('DELETE', $relativePath);
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
     * 签名请求并执行
     *
     * @return string GET/HEAD 的响应体（其他动词为空串）
     */
    private function request(string $verb, string $relativePath, string $body = ''): string
    {
        $path = '/' . ltrim($relativePath, '/');
        $contentMd5 = $body !== '' && $verb === 'PUT' ? base64_encode(md5($body, true)) : '';
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $contentType = $verb === 'PUT' ? 'application/octet-stream' : '';
        $stringToSign = $verb . "\n"
            . $contentMd5 . "\n"
            . $contentType . "\n"
            . $date . "\n"
            . "/{$this->bucket}{$path}";
        $signature = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret, true));

        $ch = curl_init($this->baseUrl($path));
        $headers = [
            'Date: ' . $date,
            'Authorization: OSS ' . $this->accessKeyId . ':' . $signature,
        ];
        if ($body !== '' && $verb === 'PUT') {
            $headers[] = 'Content-MD5: ' . $contentMd5;
            $headers[] = 'Content-Type: application/octet-stream';
        }
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $verb,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $verb === 'PUT' ? $body : null,
        ]);
        $resp = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        if ($status >= 400 || (!is_string($resp) && $status !== 204)) {
            $detail = is_string($resp) ? substr(strip_tags($resp), 0, 200) : $error;
            throw new RuntimeException("OSS {$verb} {$relativePath} 失败：HTTP {$status} {$detail}");
        }

        return is_string($resp) ? $resp : '';
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
