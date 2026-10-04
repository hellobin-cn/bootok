<?php

namespace app\service\storage;

use app\model\StorageChannel;
use RuntimeException;

/**
 * 存储渠道管理器：渠道行 → 驱动实例（进程内缓存）
 *
 * 容器装配（config/dependence.php）：
 *   - StorageInterface::class  → 默认渠道驱动（业务代码 #[Inject] 使用，不感知具体实现）
 *   - ChannelManager::class    → 需要按附件渠道路由（url/删除）时注入
 *
 * 缓存键含渠道 updated_at：渠道编辑后新实例自动生效，无需手动失效。
 */
final class ChannelManager
{
    /** @var array<string, StorageInterface> 驱动实例缓存（跨注入实例共享） */
    private static array $driverCache = [];

    /** @var array<int, StorageChannel> 渠道行缓存 */
    private static array $channelCache = [];

    /**
     * 默认渠道的驱动（无任何渠道时回退本地 public，保证系统可用）
     */
    public function default(): StorageInterface
    {
        return $this->forChannel($this->defaultChannel());
    }

    /**
     * 默认渠道行（保证非空：库中无渠道时返回未持久化的本地渠道）
     */
    public function defaultChannel(): StorageChannel
    {
        $channel = StorageChannel::where('is_default', true)->first()
            ?? StorageChannel::orderBy('id')->first();
        if ($channel === null) {
            $channel = new StorageChannel([
                'title'     => '本地存储',
                'type'      => 'local',
                'config'    => ['root' => 'public'],
                'is_default' => true,
            ]);
            $channel->id = 0;
        }

        return $this->cached($channel);
    }

    /**
     * 按附件行路由驱动（channel_id 为空/渠道已删时回退默认渠道）
     */
    public function forId(?int $channelId): StorageInterface
    {
        if ($channelId === null || $channelId <= 0) {
            return $this->default();
        }
        $channel = $this->channelById($channelId);
        if ($channel === null) {
            return $this->default();
        }

        return $this->forChannel($channel);
    }

    public function forChannel(StorageChannel $channel): StorageInterface
    {
        $key = $channel->type . ':' . md5((string) $channel->id . '|' . json_encode($channel->config) . '|' . (string) $channel->updated_at);
        if (!isset(self::$driverCache[$key])) {
            self::$driverCache[$key] = ChannelFactory::build($channel);
        }

        return self::$driverCache[$key];
    }

    /**
     * 渠道行（带缓存；渠道可能已被删除，返回 null 由调用方兜底）
     */
    public function channelById(int $id): ?StorageChannel
    {
        if (isset(self::$channelCache[$id])) {
            return self::$channelCache[$id];
        }
        $channel = StorageChannel::find($id);
        if ($channel === null) {
            return null;
        }

        return $this->cached($channel);
    }

    private function cached(StorageChannel $channel): StorageChannel
    {
        if ($channel->id > 0) {
            self::$channelCache[$channel->id] = $channel;
        }

        return $channel;
    }

    /**
     * 渠道被删除时清理缓存（模型钩子调用）
     */
    public static function forget(int $id): void
    {
        unset(self::$channelCache[$id]);
        // 驱动缓存键含渠道数据指纹，无需精确清理；渠道全量缓存重建成本极低，直接清空最稳妥
        self::$driverCache = [];
    }
}
