<?php

namespace app\model;

/**
 * 部门（数据权限的组织树）
 *
 * @property int    $id
 * @property int    $parent_id
 * @property string $name
 * @property string $path     祖先链含自身，如 1/3/7
 * @property int    $sort
 */
class Dept extends Model
{
    protected $table = 'sys_dept';
}
