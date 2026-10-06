<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\service\admin\DeptService;
use support\Request;
use support\Response;

/**
 * 部门管理端点（数据权限的组织树）
 */
class DeptController extends Controller
{
    public function __construct(private readonly DeptService $depts)
    {
        parent::__construct();
    }

    /**
     * 全量部门（平铺，前端组树/树表）
     * GET /dept/all
     */
    public function all(): Response
    {
        return $this->success($this->depts->all());
    }

    /**
     * 新建部门
     * POST /dept {parent_id, name}
     */
    public function create(Request $request): Response
    {
        try {
            $dept = $this->depts->create(
                $request->post('parent_id') !== null ? (int) $request->post('parent_id') : null,
                (string) $request->post('name', '')
            );

            return $this->success(['id' => $dept->id, 'path' => $dept->path]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 重命名（子树 path 同步迁移）
     * PUT /dept/{id} {name}
     */
    public function rename(Request $request, int $id): Response
    {
        try {
            $dept = $this->depts->rename($id, (string) $request->post('name', ''));

            return $this->success(['id' => $dept->id, 'path' => $dept->path]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除（有子部门或管理员时拒绝）
     * DELETE /dept/{id}
     */
    public function remove(Request $request, int $id): Response
    {
        try {
            $this->depts->delete($id);

            return $this->success();
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }
}
