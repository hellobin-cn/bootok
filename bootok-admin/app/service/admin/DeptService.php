<?php

namespace app\service\admin;

use app\model\Admin;
use app\model\Dept;
use RuntimeException;

/**
 * 部门服务：组织树的建立与维护（数据权限的组织载体）
 */
class DeptService
{
    /**
     * 全量部门（平铺，含 path），前端可组树或树表
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return Dept::orderBy('path')->get()
            ->map(fn (Dept $d) => [
                'id'       => $d->id,
                'parentId' => $d->parent_id,
                'name'     => $d->name,
                'path'     => $d->path,
                'sort'     => $d->sort,
                'admins'   => Admin::where('dept_id', $d->id)->count(),
            ])
            ->all();
    }

    /**
     * 新建部门（parentId 为空挂根，根哨兵 path 为空串的约定同文件夹服务）
     */
    public function create(?int $parentId, string $name): Dept
    {
        $name = $this->sanitizeName($name);
        if ($name === '') {
            throw new RuntimeException('部门名不能为空');
        }
        $parentPath = '';
        if ($parentId !== null && $parentId > 0) {
            $parent = Dept::find($parentId);
            if ($parent === null) {
                throw new RuntimeException('父部门不存在');
            }
            $parentPath = (string) $parent->path;
        }
        $dept = new Dept([
            'parent_id' => $parentId,
            'name'      => $name,
            'path'      => $parentPath === '' ? $name : $parentPath . '/' . $name,
        ]);
        $dept->save();

        return $dept;
    }

    /**
     * 重命名：path 以部门名为段，重命名需迁移自身与子树的 path
     */
    public function rename(int $id, string $newName): Dept
    {
        $dept = Dept::find($id);
        if ($dept === null) {
            throw new RuntimeException('部门不存在');
        }
        $newName = $this->sanitizeName($newName);
        if ($newName === '' || $newName === $dept->name) {
            throw new RuntimeException('部门名无效');
        }

        $oldPrefix = (string) $dept->path;
        $newPath = substr($oldPrefix, 0, -strlen((string) $dept->name)) . $newName;
        $newPrefix = $newPath . '/';

        $dept->name = $newName;
        $dept->path = $newPath;
        $dept->save();

        // 子树 path 重建（自身 path 已含新名，子树前缀为旧 path + '/'）
        foreach (Dept::where('path', 'like', $oldPrefix . '/%')->get() as $child) {
            $child->path = $newPrefix . substr((string) $child->path, strlen($oldPrefix) + 1);
            $child->save();
        }

        return $dept;
    }

    /**
     * 删除：有子部门或挂有管理员时拒绝（不做级联，防误删组织节点）
     */
    public function delete(int $id): void
    {
        $dept = Dept::find($id);
        if ($dept === null) {
            throw new RuntimeException('部门不存在');
        }
        if (Dept::where('parent_id', $id)->exists()) {
            throw new RuntimeException('存在子部门，请先处理子部门');
        }
        if (Admin::where('dept_id', $id)->exists()) {
            throw new RuntimeException('部门下仍有管理员，请先移出');
        }
        $dept->delete();
    }

    /**
     * 管理员表单用的扁平选项（按 path 排序天然呈现层级，name 缩进）
     *
     * @return array<int, array{value: int, label: string}>
     */
    public function selectOptions(): array
    {
        $options = [['value' => 0, 'label' => '未分配']];
        foreach (Dept::orderBy('path')->get() as $dept) {
            $depth = substr_count((string) $dept->path, '/');
            $options[] = ['value' => $dept->id, 'label' => str_repeat('　', $depth) . $dept->name];
        }

        return $options;
    }

    private function sanitizeName(string $name): string
    {
        return trim(str_replace(['/', '\\', "\0"], '', $name));
    }
}
