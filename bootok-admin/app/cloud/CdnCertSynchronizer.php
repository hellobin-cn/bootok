<?php

namespace app\cloud;

use RuntimeException;

/**
 * CDN HTTPS 证书同步器：把签发/续期出的证书推到云 CDN 域名的 HTTPS 配置
 *
 * 短链路设计：不经过云证书服务，直接在域名配置接口上传证书内容
 *   阿里云  SetCdnDomainSSLCertificate（cdn.aliyuncs.com，RPC，CertType=upload）
 *   腾讯云  UpdateDomainConfig（cdn.tencentcloudapi.com，TC3，ServerCertificateMode=1）
 * 每次推送即轮换：CDN 域名始终指向最新证书内容，旧证书自然被替换。
 */
class CdnCertSynchronizer
{
    /**
     * 推送证书到 CDN 域名
     *
     * @param array<string, mixed> $config 部署目标 config（cdn_domain + 云凭据）
     */
    public static function deploy(string $provider, array $config, string $domain, string $fullchain, string $privkey): void
    {
        $cdnDomain = (string) ($config['cdn_domain'] ?? '');
        if ($cdnDomain === '') {
            throw new RuntimeException('配置项 cdn_domain 不能为空');
        }
        $certName = 'bootok-' . $domain . '-' . date('YmdHis');

        if ($provider === 'aliyun') {
            self::client('cdn.aliyuncs.com', '2018-05-10', $config)->call('SetCdnDomainSSLCertificate', [
                'DomainName'   => $cdnDomain,
                'SSLProtocol'  => 'on',
                'CertType'     => 'upload',
                'CertName'     => $certName,
                'SSLPub'       => $fullchain,
                'SSLPri'       => $privkey,
            ]);

            return;
        }
        if ($provider === 'tencent') {
            self::client($config)->call('UpdateDomainConfig', [
                'Domain' => $cdnDomain,
                'Https'  => [
                    'Setting'               => 'on',
                    'ServerCertificateMode' => 1,
                    'ServerCertificate'     => $fullchain,
                    'ServerPrivateKey'      => $privkey,
                ],
            ]);

            return;
        }
        throw new RuntimeException("CDN 服务商不支持：{$provider}");
    }

    /**
     * 配置探针（只读）：验证凭据有效性与 CDN 域名归属
     *
     * @return array{ok: bool, message: string}
     */
    public static function probe(string $provider, array $config): array
    {
        $cdnDomain = (string) ($config['cdn_domain'] ?? '');
        try {
            if ($provider === 'aliyun') {
                if ($cdnDomain === '') {
                    $data = self::client('cdn.aliyuncs.com', '2018-05-10', $config)->call('DescribeCdnUserDomains', ['PageSize' => 5]);
                    $names = array_column($data['Domains']['DataPage'] ?? [], 'DomainName');
                    $list = $names === [] ? '账号下暂无加速域名' : '账号下域名：' . implode('、', $names);

                    return ['ok' => true, 'message' => '阿里云凭据有效（' . $list . '）'];
                }
                // 不带 FunctionNames（https_domain_cert_config 已不被支持）。
                // 注意：该接口的 FunctionArgs 响应不稳定（含大证书 PEM 时常被截断），不做 HTTPS 状态解析，
                // 部署结果以本系统部署记录与实际访问为准
                self::client('cdn.aliyuncs.com', '2018-05-10', $config)->call('DescribeCdnDomainConfigs', [
                    'DomainName' => $cdnDomain,
                ]);

                return ['ok' => true, 'message' => "阿里云凭据有效，域名 {$cdnDomain} 存在且可管理"];
            }
            if ($provider === 'tencent') {
                $filter = $cdnDomain !== '' ? ['Filters' => [['name' => 'domain', 'value' => [$cdnDomain]]]] : [];
                $data = self::clientTencent($config)->call('DescribeDomainsConfig', $filter + ['Offset' => 0, 'Limit' => 5]);
                $found = count($data['Domains'] ?? []) > 0;
                $message = $cdnDomain === ''
                    ? '腾讯云凭据有效（账号下加速域名 ' . ($data['TotalCount'] ?? 0) . ' 个）'
                    : ($found ? "腾讯云凭据有效，域名 {$cdnDomain} 存在且可管理" : "腾讯云凭据有效，但账号下未找到域名 {$cdnDomain}");

                return ['ok' => $cdnDomain === '' || $found, 'message' => $message];
            }
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            // 权限类错误给出自助指引（凭据本身有效，仅缺少 CDN 产品权限）
            if (str_contains($message, 'not authorized') || str_contains($message, 'Unauthorized')) {
                return ['ok' => false, 'message' => '凭据有效但缺少 CDN 权限：请为该 AccessKey 授权对应 CDN 操作权限（阿里云 cdn:*，腾讯云 cdn:DescribeDomainsConfig 等）'];
            }

            return ['ok' => false, 'message' => $message];
        }

        return ['ok' => false, 'message' => "CDN 服务商不支持：{$provider}"];
    }

    private static function client(string $endpoint, string $version, array $config): AliyunClient
    {
        if (empty($config['access_key_id']) || empty($config['access_key_secret'])) {
            throw new RuntimeException('阿里云 CDN 需要 access_key_id / access_key_secret 凭据');
        }

        return new AliyunClient($endpoint, $version, (string) $config['access_key_id'], (string) $config['access_key_secret']);
    }

    private static function clientTencent(array $config): TencentClient
    {
        if (empty($config['secret_id']) || empty($config['secret_key'])) {
            throw new RuntimeException('腾讯云 CDN 需要 secret_id / secret_key 凭据');
        }

        return new TencentClient('cdn.tencentcloudapi.com', 'cdn', '2018-06-01', (string) $config['secret_id'], (string) $config['secret_key']);
    }
}
