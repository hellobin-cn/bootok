<?php

namespace app\cloud\Contract;

/**
 * CDN 证书部署策略契约
 *
 * 每个云厂商一个实现，内聚各自的端点、版本、Action 与参数结构；
 * 签发/续期出的证书经策略实现推送到 CDN 域名的 HTTPS 配置。
 */
interface CdnCertStrategyInterface
{
    /**
     * 服务商标识（aliyun/tencent …），部署目标按此选择策略
     */
    public function name(): string;

    /**
     * 推送证书到 CDN 域名的 HTTPS 配置（每次推送即轮换到最新证书）
     *
     * @param array<string, mixed> $config 部署目标配置（加速域名 + 云凭据）
     * @param string               $domain 证书绑定的主域名（用于证书命名）
     * @throws RuntimeException 推送失败
     */
    public function deploy(string $domain, string $fullchain, string $privkey, array $config): void;

    /**
     * 配置探针（只读）：验证凭据有效性与 CDN 域名归属
     *
     * @param array<string, mixed> $config 部署目标配置
     * @return array{ok: bool, message: string}
     */
    public function probe(array $config): array;
}
