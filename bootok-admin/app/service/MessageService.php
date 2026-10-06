<?php

namespace app\service;

use app\model\Admin;
use app\model\Message;
use app\service\im\ImAlertService;
use support\Log;
use Throwable;

/**
 * 站内消息服务：系统事件的统一通知出口（任务失败/证书异常/安全告警）
 * send 永不抛出——通知失败只记日志，绝不影响业务主流程
 */
class MessageService
{
    /** 相同收件人+标题的去重窗口（秒），分钟级任务反复失败时不刷屏 */
    private const DEDUP_WINDOW = 600;

    /** 类型白名单，防调用方拼错导致前端无图标 */
    public const TYPES = [Message::TYPE_NOTICE, Message::TYPE_TASK, Message::TYPE_SSL, Message::TYPE_SECURITY];

    /** 调用方侧的类型常量别名（不必为常量引入模型） */
    public const TYPE_NOTICE = Message::TYPE_NOTICE;
    public const TYPE_TASK = Message::TYPE_TASK;
    public const TYPE_SSL = Message::TYPE_SSL;
    public const TYPE_SECURITY = Message::TYPE_SECURITY;

    public function send(int $adminId, string $title, string $content = '', string $type = Message::TYPE_NOTICE, string $link = ''): void
    {
        try {
            if ($adminId <= 0 || $title === '') {
                return;
            }
            $dup = Message::where('receiver_admin_id', $adminId)
                ->where('title', $title)
                ->where('created_at', '>=', date('Y-m-d H:i:s', time() - self::DEDUP_WINDOW))
                ->first();
            if ($dup !== null) {
                return;
            }
            Message::create([
                'receiver_admin_id' => $adminId,
                'title'             => mb_substr($title, 0, 200),
                'content'           => mb_substr($content, 0, 2000),
                'type'              => in_array($type, self::TYPES, true) ? $type : Message::TYPE_NOTICE,
                'link'              => mb_substr($link, 0, 255),
            ]);
            // 并行出口：站内落库的同时推送到群机器人（内部吞异常，配置未启用时静默）
            (new ImAlertService())->send($title, $content);
        } catch (Throwable $e) {
            Log::warning('站内消息发送失败: ' . $e->getMessage());
        }
    }

    /** 系统事件的默认收件人：全部超级管理员 */
    public function sendToSupers(string $title, string $content = '', string $type = Message::TYPE_NOTICE, string $link = ''): void
    {
        foreach (Admin::query()->where('is_super', 1)->pluck('admin_id') as $id) {
            $this->send((int) $id, $title, $content, $type, $link);
        }
    }

    public function unreadCount(int $adminId): int
    {
        return (int) Message::where('receiver_admin_id', $adminId)->where('is_read', 0)->count();
    }
}
