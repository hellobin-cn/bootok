<?php

namespace app\model;

/**
 * 存储目录（与渠道内真实前缀完全对应，按渠道隔离）
 *
 * @property int    $id
 * @property int    $channel_id
 * @property int    $parent_id
 * @property string $name
 * @property string $path        相对渠道根的全路径
 */
class Folder extends Model
{
    protected $table = 'sys_folder';
}
