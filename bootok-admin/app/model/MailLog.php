<?php

namespace app\model;

use app\attribute\AutoResource;

/**
 * 邮件发送记录（结果与渲染后内容快照；重发直接用快照，不重渲染）
 *
 * @property int    $id
 * @property int    $channel_id    发送渠道（未保存配置的测试为空）
 * @property string $template_code 所用模板标识（自由内容为空）
 * @property string $to_address    收件人（逗号分隔）
 * @property string $subject       邮件主题
 * @property string $body          渲染后内容快照
 * @property int    $status        1成功 0失败
 * @property string $error         失败原因
 * @property int    $cost_ms       耗时毫秒
 */
#[AutoResource(name: '发送记录', only: ['getRows', 'getItem', 'delete'])]
class MailLog extends Model
{
    protected $table = 'sys_mail_log';
}
