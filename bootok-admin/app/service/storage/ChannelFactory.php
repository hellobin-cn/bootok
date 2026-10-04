<?php

namespace app\service\storage;

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
            'oss'   => new OssDriver($config),
            'cos'   => new CosDriver($config),
            default => throw new RuntimeException("未知存储渠道类型：{$channel->type}"),
        };
    }
}
