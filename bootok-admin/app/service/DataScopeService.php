<?php

namespace app\service;

use app\model\Admin;
use app\model\Dept;
use app\model\Group;
use app\model\Model;
use Illuminate\Database\Eloquent\Builder;
use RuntimeException;

/**
 * 数据权限服务：按权限组的四级数据范围对行级可见性做统一注入
 *
 * 范围语义（sys_group.data_scope）：
 *   1 全部数据 / 2 本部门及以下 / 3 仅本部门 / 4 仅本人
 * 模型无 opt-in 声明：查询按表列自动生效——有 dept_id 列走部门维度，
 * 有 admin_id 列走创建人维度；两者皆无且范围非「全部」时失败安全返回空集。
 * 部门范围额外并入「创建人是自己」，避免自建行因未挂部门而不可见。
 * 超管恒为全部。
 */
class DataScopeService
{
    public const SCOPE_ALL = 1;
    public const SCOPE_DEPT_TREE = 2;
    public const SCOPE_DEPT = 3;
    public const SCOPE_SELF = 4;

    /**
     * 行级可见上下文
     *
     * @return array{type: int, deptId: int, adminId: int}
     */
    public function context(int $adminId): array
    {
        $admin = Admin::find($adminId);
        if ($admin === null || $admin->is_super) {
            return ['type' => self::SCOPE_ALL, 'deptId' => 0, 'adminId' => $adminId];
        }
        $group = Group::find($admin->group_id);
        $scope = (int) ($group->data_scope ?? self::SCOPE_ALL);

        return ['type' => $scope, 'deptId' => (int) ($admin->dept_id ?? 0), 'adminId' => $adminId];
    }

    /**
     * 列表查询注入：filter() 之后调用
     */
    public function apply(Builder $query, Model $model, int $adminId): Builder
    {
        $ctx = $this->context($adminId);
        if ($ctx['type'] === self::SCOPE_ALL) {
            return $query;
        }
        $columns = $model->getColumns();
        $hasDept = in_array('dept_id', $columns, true);
        $hasOwner = in_array('admin_id', $columns, true);

        if ($hasDept && $ctx['deptId'] > 0 && in_array($ctx['type'], [self::SCOPE_DEPT_TREE, self::SCOPE_DEPT], true)) {
            $ids = $ctx['type'] === self::SCOPE_DEPT
                ? [$ctx['deptId']]
                : $this->visibleDeptIds($ctx['deptId']);

            return $query->where(function ($q) use ($ids, $adminId) {
                $q->whereIn('dept_id', $ids)->orWhere('admin_id', $adminId);
            });
        }
        if ($hasOwner) {
            return $query->where('admin_id', $adminId);
        }

        // 范围非「全部」但模型无可限定列：失败安全，返回空集
        return $query->whereRaw('1=0');
    }

    /**
     * 单行可见性校验（edit/delete/getItem 防越权）
     */
    public function canRow(Model $row, int $adminId): bool
    {
        $ctx = $this->context($adminId);
        if ($ctx['type'] === self::SCOPE_ALL) {
            return true;
        }
        $columns = $row->getColumns();
        $rowDept = (int) ($row->dept_id ?? 0);
        $rowOwner = (int) ($row->admin_id ?? -1);
        $hasDept = in_array('dept_id', $columns, true);
        $hasOwner = in_array('admin_id', $columns, true);

        if ($hasOwner && $rowOwner === $adminId) {
            return true;
        }
        if ($hasDept && $rowDept > 0 && $ctx['deptId'] > 0) {
            if ($ctx['type'] === self::SCOPE_DEPT && $rowDept === $ctx['deptId']) {
                return true;
            }
            if ($ctx['type'] === self::SCOPE_DEPT_TREE && in_array($rowDept, $this->visibleDeptIds($ctx['deptId']), true)) {
                return true;
            }
        }
        if (!$hasDept && !$hasOwner) {
            // 无可判定列：宁可拒绝
            return false;
        }

        return false;
    }

    /**
     * 可见部门 id 集（本部门 + path 前缀子树；path 含自身）
     *
     * @return int[]
     */
    public function visibleDeptIds(int $deptId): array
    {
        $dept = Dept::find($deptId);
        if ($dept === null) {
            return [];
        }

        return Dept::where('id', $deptId)
            ->orWhere('path', 'like', $dept->path . '/%')
            ->pluck('id')
            ->all();
    }
}
