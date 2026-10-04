<?php

namespace app\cloud;

use RuntimeException;

/**
 * 腾讯云开放 API 3.0 通用客户端（TC3-HMAC-SHA256 签名，纯 PHP 无 SDK）
 *
 * 适用 cdn.tencentcloudapi.com / ssl.tencentcloudapi.com 等一切 API 3.0 端点；
 * 签名链与 DNSPod 产品一致（SignKey → HttpString → StringToSign → Signature）。
 */
class TencentClient
{
    public function __construct(
        private readonly string $host,          // 如 cdn.tencentcloudapi.com
        private readonly string $service,       // 如 cdn
        private readonly string $version,       // 如 2018-06-01
        private readonly string $secretId,
        private readonly string $secretKey,
    ) {
    }

    /**
     * 调用 Action，返回 Response 体；业务错误抛 RuntimeException
     *
     * @param array<string, mixed> $params 业务参数（JSON 载荷）
     * @return array<string, mixed>
     */
    public function call(string $action, array $params = []): array
    {
        $payload = json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $timestamp = time();
        $date = gmdate('Y-m-d', $timestamp);

        $canonicalRequest = 'POST'
            . "\n/\n"
            . "\n"
            . 'content-type:application/json; charset=utf-8' . "\n"
            . 'host:' . $this->host . "\n"
            . "\n"
            . 'content-type;host'
            . "\n" . hash('sha256', $payload);
        $stringToSign = 'TC3-HMAC-SHA256'
            . "\n" . $timestamp
            . "\n" . $date . '/' . $this->service . '/tc3_request'
            . "\n" . hash('sha256', $canonicalRequest);
        $kDate = hash_hmac('sha256', $date, 'TC3' . $this->secretKey, true);
        $kSigning = hash_hmac('sha256', 'tc3_request', hash_hmac('sha256', $this->service, $kDate, true), true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);
        $authorization = 'TC3-HMAC-SHA256 '
            . 'Credential=' . $this->secretId . '/' . $date . '/' . $this->service . '/tc3_request, '
            . 'SignedHeaders=content-type;host, '
            . 'Signature=' . $signature;

        try {
            $resp = \BootOk\Http::withHeaders([
                'X-TC-Action'    => $action,
                'X-TC-Version'   => $this->version,
                'X-TC-Timestamp' => (string) $timestamp,
            ])->timeout(30)
                ->withBody((string) $payload, 'application/json; charset=utf-8')
                ->post('https://' . $this->host . '/');
        } catch (\Throwable $e) {
            throw new RuntimeException("腾讯云 {$action} 传输失败：" . $e->getMessage());
        }
        $data = json_decode($resp->body(), true) ?? [];
        $error = $data['Response']['Error'] ?? null;
        if ($error !== null) {
            throw new RuntimeException(
                '腾讯云 ' . $action . ' 失败: [' . ($error['Code'] ?? '') . '] ' . ($error['Message'] ?? 'unknown')
            );
        }

        return $data['Response'] ?? [];
    }
}
