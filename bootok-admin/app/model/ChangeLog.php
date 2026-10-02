<?php

namespace app\model;

use app\attribute\AutoResource;

/**
 * 字段级变更审计（只读资源）
 * 记录模型声明 auditChange=true 的资源在 /auto 写操作中的字段 diff
 */
#[AutoResource(name: '变更审计', only: ['getRows'], read: 'change-log.rows')]
class ChangeLog extends Model
{
    protected $table = 'sys_change_log';

    public $timestamps = false;
}
