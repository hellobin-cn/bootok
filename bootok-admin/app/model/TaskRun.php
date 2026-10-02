<?php

namespace app\model;

use app\attribute\AutoResource;

/**
 * 计划任务执行记录（只读资源）
 * @property string $name
 * @property int $status
 */
#[AutoResource(name: '任务执行记录', only: ['getRows'], read: 'task.rows')]
class TaskRun extends Model
{
    protected $table = 'sys_task_run';

    public $timestamps = false;
}
