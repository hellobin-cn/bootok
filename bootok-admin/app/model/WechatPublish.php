<?php

namespace app\model;

/**
 * 公众号发布记录（sys_wechat_publish）：文章 ↔ 草稿 ↔ 发布任务 ↔ 链接
 *
 * @property int    $id
 * @property int    $article_id
 * @property string $title
 * @property string $draft_media_id
 * @property string $publish_id
 * @property string $url
 * @property int    $status
 * @property string $error
 */
class WechatPublish extends Model
{
    protected $table = 'sys_wechat_publish';
    protected $primaryKey = 'id';
}
