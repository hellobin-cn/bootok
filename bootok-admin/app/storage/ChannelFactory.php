<?php

namespace app\storage;

use RuntimeException;

/**
 * 渠道工厂：存储渠道行 → 驱动实例
 *
 * 全项目唯一允许点名具体驱动的位置（工厂职责所在），
 * 业务代码一律经 StorageInterface / ChannelManager 使用驱动。
 */
final class ChannelFactory
{
    public static function build(\app\model\StorageChannel $channel): StorageInterface
    {
        $config = is_array($channel->config) ? $channel->config : [];

        return match ($channel->type) {
            'local' => new LocalDriver($config),
            // 阶段 2/3 落地：纯 PHP 签名实现（无 SDK），config: bucket/endpoint/access_key_id/access_key_secret
            'oss'   => throw new RuntimeException('阿里云 OSS 驱动尚未接入，敬请期待'),
            'cos'   => throw new RuntimeException('腾讯云 COS 驱动尚未接入，敬请期待'),
            default => throw new RuntimeException("未知存储渠道类型：{$channel->type}"),
        };
    }
}
