<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\enum\Code;
use app\model\Backup;
use app\service\backup\BackupService;
use support\Request;
use support\Response;
use Throwable;

/**
 * 数据库备份端点（仅超管）：列表/立即备份/下载/恢复/删除
 */
class BackupController extends Controller
{
    public function __construct(private readonly BackupService $backups)
    {
        parent::__construct();
    }

    private function guard(): ?Response
    {
        if (!$this->request->getCustomData('isSuper', 0)) {
            return $this->error('仅超级管理员可操作备份', Code::FORBIDDEN->value);
        }
        return null;
    }

    /**
     * GET /backup/rows
     */
    public function rows(): Response
    {
        if ($res = $this->guard()) {
            return $res;
        }
        $pageSize = max(1, min(100, (int) $this->request->input('page_size', 15)));
        $paginator = Backup::orderBy('backup_id', 'desc')
            ->paginate($pageSize, ['*'], 'page', (int) $this->request->input('page', 1));
        return $this->success($paginator);
    }

    /**
     * POST /backup/run：立即备份（同步执行，常规数据量秒级）
     */
    public function run(): Response
    {
        if ($res = $this->guard()) {
            return $res;
        }
        try {
            $row = $this->backups->run('manual');
        } catch (Throwable $e) {
            return $this->error('备份失败：' . mb_substr($e->getMessage(), 0, 200));
        }
        return $this->success($row, "备份完成（{$row->tables} 张表）");
    }

    /**
     * GET /backup/{id}/download：流式下载压缩包
     */
    public function download(int $id): Response
    {
        if ($res = $this->guard()) {
            return $res;
        }
        $backup = Backup::find($id);
        if ($backup === null) {
            return $this->error('备份不存在');
        }
        try {
            $content = $this->backups->content($backup);
        } catch (Throwable $e) {
            return $this->error($e->getMessage());
        }
        return response($content)->withHeaders([
            'Content-Type'        => 'application/gzip',
            'Content-Disposition' => "attachment; filename*=UTF-8''" . rawurlencode($backup->filename),
        ]);
    }

    /**
     * POST /backup/{id}/restore：恢复（危险操作，前端二次确认）
     */
    public function restore(int $id): Response
    {
        if ($res = $this->guard()) {
            return $res;
        }
        $backup = Backup::find($id);
        if ($backup === null) {
            return $this->error('备份不存在');
        }
        try {
            $result = $this->backups->restore($backup);
        } catch (Throwable $e) {
            return $this->error('恢复失败：' . mb_substr($e->getMessage(), 0, 300));
        }
        return $this->success($result, "恢复完成（{$result['statements']} 条语句）");
    }

    /**
     * DELETE /backup/{id}
     */
    public function remove(int $id): Response
    {
        if ($res = $this->guard()) {
            return $res;
        }
        $backup = Backup::find($id);
        if ($backup === null) {
            return $this->error('备份不存在');
        }
        $this->backups->remove($backup);
        return $this->success('删除成功');
    }
}
