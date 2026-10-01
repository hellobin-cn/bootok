<?php

namespace app\model;

use app\attribute\AutoResource;

/**
 * 操作日志，只读资源
 * @property int $id
 * @property int $admin_id
 * @property string $admin_name
 * @property string $method
 * @property string $path
 * @property string $ip
 * @property string $params
 */
#[AutoResource(name: '操作日志', only: ['getRows'], read: 'operation-log.rows')]
class OperationLog extends Model
{
    protected $table = 'sys_operation_log';

    // created_at 由数据库默认值生成，关闭Eloquent时间戳（避免 UPDATED_AT=null 的序列化兼容问题）
    public $timestamps = false;
}
