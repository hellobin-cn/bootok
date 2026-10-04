<?php

namespace app\service\cloud\client;

use app\service\cloud\contract\CloudClientInterface;
use RuntimeException;

/**
 * 阿里云开放 API 通用客户端（RPC 风格，HMAC-SHA1 签名，纯 PHP 无 SDK）
 *
 * 适用 cdn.aliyuncs.com 等一切 RPC 产品端点；签名算法与 DNS 产品一致
 * （参数排序 → URL 编码拼接 → GET&%2F& 待签 → HMAC-SHA1）。
 * 经 bootok/http HTTP 层发送：大参数（如证书 PEM）走 POST 表单。
 */
class AliyunClient implements CloudClientInterface
{
    public function __construct(
        private readonly string $endpoint,      // 如 cdn.aliyuncs.com
        private readonly string $version,       // 如 2018-05-10
        private readonly string $accessKeyId,
        private readonly string $accessKeySecret,
    ) {
    }

    /**
     * 调用 RPC Action，返回解码后的响应；业务错误抛 RuntimeException
     *
     * @param array<string, mixed> $params 业务参数
     * @return array<string, mixed>
     */
    public function call(string $action, array $params = []): array
    {
        $common = [
            'Action'           => $action,
            'Format'           => 'JSON',
            'Version'          => $this->version,
            'AccessKeyId'      => $this->accessKeyId,
            'SignatureMethod'  => 'HMAC-SHA1',
            'SignatureNonce'   => uniqid('', true),
            'SignatureVersion' => '1.0',
            'Timestamp'        => gmdate('Y-m-d\TH:i:s\Z'),
        ];
        $all = array_merge($common, $params);

        ksort($all);
        $query = [];
        foreach ($all as $key => $value) {
            $query[] = rawurlencode($key) . '=' . rawurlencode((string) $value);
        }
        $stringToSign = 'POST&%2F&' . rawurlencode(implode('&', $query));
        $all['Signature'] = base64_encode(hash_hmac('sha1', $stringToSign, $this->accessKeySecret . '&', true));

        try {
            $resp = \BootOk\Http::withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                ->timeout(30)
                ->withBody(http_build_query($all), 'application/x-www-form-urlencoded')
                ->post('https://' . $this->endpoint . '/');
        } catch (\Throwable $e) {
            throw new RuntimeException("阿里云 {$action} 传输失败：" . $e->getMessage());
        }
        $data = json_decode($resp->body(), true) ?? [];
        if (isset($data['Code'])) {
            throw new RuntimeException('阿里云 ' . $action . ' 失败: ' . ($data['Message'] ?? $data['Code']));
        }
        if ($resp->status() >= 400) {
            throw new RuntimeException('阿里云 ' . $action . ' 失败：HTTP ' . $resp->status() . ' ' . mb_substr($resp->body(), 0, 300));
        }

        return $data;
    }
}
