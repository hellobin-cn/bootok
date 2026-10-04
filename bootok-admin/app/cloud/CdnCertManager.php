<?php

namespace app\cloud;

use app\cloud\Contract\CdnCertStrategyInterface;
use RuntimeException;

/**
 * CDN 证书部署策略管理器：按服务商标识分发到对应策略
 *
 * 策略清单在容器装配处（config/dependence.php）注册——新增云厂商
 * 只需实现 CdnCertStrategyInterface 并注册一行，存量代码零修改。
 */
class CdnCertManager
{
    /** @var array<string, CdnCertStrategyInterface> */
    private array $strategies = [];

    /**
     * @param CdnCertStrategyInterface[] $strategies
     */
    public function __construct(array $strategies)
    {
        foreach ($strategies as $strategy) {
            $this->strategies[$strategy->name()] = $strategy;
        }
    }

    /**
     * 推送证书到指定服务商的 CDN 域名
     *
     * @param array<string, mixed> $config 部署目标配置
     */
    public function deploy(string $provider, array $config, string $domain, string $fullchain, string $privkey): void
    {
        $this->strategy($provider)->deploy($domain, $fullchain, $privkey, $config);
    }

    /**
     * 配置探针（只读）：验证凭据有效性与域名归属
     *
     * @param array<string, mixed> $config 部署目标配置
     * @return array{ok: bool, message: string}
     */
    public function probe(string $provider, array $config): array
    {
        return $this->strategy($provider)->probe($config);
    }

    private function strategy(string $provider): CdnCertStrategyInterface
    {
        return $this->strategies[$provider]
            ?? throw new RuntimeException("CDN 服务商不支持：{$provider}");
    }
}
