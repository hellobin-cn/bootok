<?php

namespace app\service\cloud\strategy;

use app\service\cloud\AliyunClient;
use app\service\cloud\contract\CdnCertStrategyInterface;
use RuntimeException;

/**
 * 阿里云 CDN 证书部署策略
 *
 * 端点、版本、Action 与参数结构均为阿里云 CDN（2018-05-10）领域知识，
 * 内聚于本实现；上层只依赖 CdnCertStrategyInterface。
 * 已知坑：DescribeCdnDomainConfigs 的 FunctionArgs 响应不稳定（含大证书
 * PEM 时截断），探针不做 HTTPS 状态解析。
 */
class AliyunCdnCertStrategy implements CdnCertStrategyInterface
{
    public function name(): string
    {
        return 'aliyun';
    }

    public function deploy(string $domain, string $fullchain, string $privkey, array $config): void
    {
        $cdnDomain = (string) ($config['cdn_domain'] ?? '');
        if ($cdnDomain === '') {
            throw new RuntimeException('配置项 cdn_domain 不能为空');
        }
        $this->client($config)->call('SetCdnDomainSSLCertificate', [
            'DomainName'  => $cdnDomain,
            'SSLProtocol' => 'on',
            'CertType'    => 'upload',
            'CertName'    => 'bootok-' . $domain . '-' . date('YmdHis'),
            'SSLPub'      => $fullchain,
            'SSLPri'      => $privkey,
        ]);
    }

    public function probe(array $config): array
    {
        $cdnDomain = (string) ($config['cdn_domain'] ?? '');
        try {
            if ($cdnDomain === '') {
                $data = $this->client($config)->call('DescribeCdnUserDomains', ['PageSize' => 5]);
                $names = array_column($data['Domains']['DataPage'] ?? [], 'DomainName');
                $list = $names === [] ? '账号下暂无加速域名' : '账号下域名：' . implode('、', $names);

                return ['ok' => true, 'message' => '阿里云凭据有效（' . $list . '）'];
            }
            $this->client($config)->call('DescribeCdnDomainConfigs', [
                'DomainName' => $cdnDomain,
            ]);

            return ['ok' => true, 'message' => "阿里云凭据有效，域名 {$cdnDomain} 存在且可管理"];
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            // 权限类错误给出自助指引（凭据本身有效，仅缺少 CDN 产品权限）
            if (str_contains($message, 'not authorized') || str_contains($message, 'Unauthorized')) {
                return ['ok' => false, 'message' => '凭据有效但缺少 CDN 权限：请为该 AccessKey 授权对应 CDN 操作权限（如 cdn:*）'];
            }

            return ['ok' => false, 'message' => $message];
        }
    }

    private function client(array $config): AliyunClient
    {
        if (empty($config['access_key_id']) || empty($config['access_key_secret'])) {
            throw new RuntimeException('阿里云 CDN 需要 access_key_id / access_key_secret 凭据');
        }

        return new AliyunClient('cdn.aliyuncs.com', '2018-05-10', (string) $config['access_key_id'], (string) $config['access_key_secret']);
    }
}
