<?php

namespace app\model;

/**
 * 站内消息（sys_message）：系统事件产生的管理员通知
 * 个人数据，不经 /auto 暴露；读写都限定 receiver_admin_id=当前登录人
 *
 * @property int    $message_id
 * @property int    $receiver_admin_id
 * @property string $title
 * @property string $content
 * @property string $type
 * @property string $link
 * @property int    $is_read
 * @property string|null $read_at
 */
class Message extends Model
{
    public const TYPE_NOTICE = 'notice';
    public const TYPE_TASK = 'task';
    public const TYPE_SSL = 'ssl';
    public const TYPE_SECURITY = 'security';

    protected $table = 'sys_message';
    protected $primaryKey = 'message_id';
}
