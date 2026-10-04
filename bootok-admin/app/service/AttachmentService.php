<?php

namespace app\service;

use app\model\Attachment;
use app\service\storage\ChannelManager;

/**
 * 附件访问服务：URL 生成与对象删除的统一出口
 *
 * 附件可能分布在任意渠道（local/oss/cos + CDN 绑定），
 * URL 一律经本服务按附件所属渠道路由生成，调用方不得自行拼路径。
 */
class AttachmentService
{
    public function __construct(private readonly ChannelManager $channels)
    {
    }

    /**
     * 附件的访问 URL（按所属渠道路由；渠道已删回退默认渠道）
     */
    public function url(Attachment $attachment): string
    {
        return $this->channels->forId($attachment->channel_id)->url(ltrim((string) $attachment->path, '/'));
    }

    /**
     * 删除附件的存储对象（按所属渠道路由；对象不存在静默成功）
     */
    public function deleteObject(Attachment $attachment): void
    {
        $this->channels->forId($attachment->channel_id)->delete(ltrim((string) $attachment->path, '/'));
    }

    /**
     * 供模型访问器使用的静态入口（保持模型层轻量）
     */
    public static function urlFor(Attachment $attachment): string
    {
        // 容器装配，避免业务代码手写驱动
        /** @var self $service */
        $service = \support\Container::get(self::class);

        return $service->url($attachment);
    }
}
