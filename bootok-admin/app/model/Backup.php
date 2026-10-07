<?php

namespace app\model;

/**
 * 数据库备份记录（sys_backup）：文件在存储渠道，本表只存元数据
 *
 * @property int    $backup_id
 * @property string $filename
 * @property string $object
 * @property int    $size
 * @property int    $tables
 * @property string $trigger
 * @property int    $status
 * @property int    $duration_ms
 * @property string $error
 */
class Backup extends Model
{
    protected $table = 'sys_backup';
    protected $primaryKey = 'backup_id';
}
