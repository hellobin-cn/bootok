<?php

namespace app\cloud\Contract;

/**
 * 云平台开放 API 客户端契约
 *
 * 隔离各云的签名与传输协议差异（阿里云 RPC / 腾讯云 TC3 …），
 * 业务代码只依赖本接口，具体实现按云厂商装配。
 */
interface CloudClientInterface
{
    /**
     * 调用云 API 的指定 Action
     *
     * @param array<string, mixed> $params 业务参数
     * @return array<string, mixed> 响应数据（网关信封已剥离）
     * @throws RuntimeException 网络或业务错误
     */
    public function call(string $action, array $params = []): array;
}
