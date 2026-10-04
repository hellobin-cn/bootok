<?php

namespace app\cloud\Strategy;

use app\cloud\Contract\CdnCertStrategyInterface;
use app\cloud\TencentClient;
use RuntimeException;

/**
 * 腾讯云 CDN 证书部署策略
 *
 * API 版本 2018-06-06（无 2018-06-01；版本错误时网关走坏解析路径，
 * 报误导性的 Url key and value should be split by '='）。
 * 证书配置结构与官方 SDK Https/ServerCert 模型对齐（PascalCase 键）。
 */
class TencentCdnCertStrategy implements CdnCertStrategyInterface
{
    public function name(): string
    {
        return 'tencent';
    }

    public function deploy(string $domain, string $fullchain, string $privkey, array $config): void
    {
        $cdnDomain = (string) ($config['cdn_domain'] ?? '');
        if ($cdnDomain === '') {
            throw new RuntimeException('配置项 cdn_domain 不能为空');
        }
        $this->client($config)->call('UpdateDomainConfig', [
            'Domain' => $cdnDomain,
            'Https'  => [
                'Switch'   => 'on',
                'CertInfo' => [
                    'CertName'    => 'bootok-' . $domain . '-' . date('YmdHis'),
                    'Certificate' => $fullchain,
                    'PrivateKey'  => $privkey,
                ],
            ],
        ]);
    }

    public function probe(array $config): array
    {
        $cdnDomain = (string) ($config['cdn_domain'] ?? '');
        try {
            $filter = $cdnDomain !== '' ? ['Filters' => [['Name' => 'domain', 'Value' => [$cdnDomain]]]] : [];
            $data = $this->client($config)->call('DescribeDomains', $filter + ['Offset' => 0, 'Limit' => 5]);
            $found = count($data['Domains'] ?? []) > 0;
            $message = $cdnDomain === ''
                ? '腾讯云凭据有效（账号下加速域名 ' . ($data['TotalCount'] ?? 0) . ' 个）'
                : ($found ? "腾讯云凭据有效，域名 {$cdnDomain} 存在且可管理" : "腾讯云凭据有效，但账号下未找到域名 {$cdnDomain}");

            return ['ok' => $cdnDomain === '' || $found, 'message' => $message];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (str_contains($message, 'not authorized') || str_contains($message, 'Unauthorized')) {
                return ['ok' => false, 'message' => '凭据有效但缺少 CDN 权限：请为该子账号授权对应 CDN 操作权限'];
            }

            return ['ok' => false, 'message' => $message];
        }
    }

    private function client(array $config): TencentClient
    {
        if (empty($config['secret_id']) || empty($config['secret_key'])) {
            throw new RuntimeException('腾讯云 CDN 需要 secret_id / secret_key 凭据');
        }

        return new TencentClient('cdn.tencentcloudapi.com', 'cdn', '2018-06-06', (string) $config['secret_id'], (string) $config['secret_key']);
    }
}
