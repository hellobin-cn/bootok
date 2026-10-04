<?php

namespace app\service\mail;

use app\model\MailChannel;
use Symfony\Component\Mailer\Mailer;
use Symfony\Component\Mailer\Transport\TransportInterface;

/**
 * 邮件渠道管理器：渠道行 → Mailer 实例（进程内缓存）
 *
 * 缓存键含渠道 updated_at：渠道编辑后新实例自动生效，模型钩子（MailChannel::afterSaved/afterDeleted）
 * 额外调用 forget 双保险。
 */
final class MailChannelManager
{
    /** @var array<string, Mailer> Mailer 实例缓存（跨注入实例共享） */
    private static array $mailerCache = [];

    /** @var array<int, MailChannel> 渠道行缓存 */
    private static array $channelCache = [];

    /**
     * 默认渠道行（未配置任何渠道时返回 null，由 MailService 报错）
     */
    public function defaultChannel(): ?MailChannel
    {
        $channel = MailChannel::where('is_default', true)->first()
            ?? MailChannel::orderBy('id')->first();

        return $channel === null ? null : $this->cached($channel);
    }

    /**
     * 按渠道路由（id 为空/渠道已删时回退默认渠道；无任何渠道返回 null）
     */
    public function channelFor(int|string|null $channelId): ?MailChannel
    {
        if ($channelId === null || (int) $channelId <= 0) {
            return $this->defaultChannel();
        }

        return $this->channelById((int) $channelId) ?? $this->defaultChannel();
    }

    public function channelById(int $id): ?MailChannel
    {
        if (isset(self::$channelCache[$id])) {
            return self::$channelCache[$id];
        }
        $channel = MailChannel::find($id);
        if ($channel === null) {
            return null;
        }

        return $this->cached($channel);
    }

    public function mailerFor(MailChannel $channel): Mailer
    {
        $key = md5((string) $channel->id . '|' . json_encode($channel->config) . '|' . (string) $channel->updated_at);
        if (!isset(self::$mailerCache[$key])) {
            self::$mailerCache[$key] = new Mailer($this->transportFor($channel));
        }

        return self::$mailerCache[$key];
    }

    /**
     * 传输器获取（独立出口供「测试当前配置」直连校验，不经 Mailer 缓存）
     */
    public function transportFor(MailChannel $channel): TransportInterface
    {
        return TransportFactory::build($channel);
    }

    private function cached(MailChannel $channel): MailChannel
    {
        if ($channel->id > 0) {
            self::$channelCache[$channel->id] = $channel;
        }

        return $channel;
    }

    /**
     * 渠道被编辑/删除时清理缓存（模型钩子调用）
     */
    public static function forget(int $id): void
    {
        unset(self::$channelCache[$id]);
        // Mailer 缓存键含渠道数据指纹，直接清空最稳妥
        self::$mailerCache = [];
    }
}
