<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\model\Folder;
use app\service\storage\FolderService;
use support\Request;
use support\Response;

/**
 * 文件管理器目录端点（按渠道隔离的目录树，与渠道内真实前缀对应）
 */
class FolderController extends Controller
{
    public function __construct(private readonly FolderService $folders)
    {
        parent::__construct();
    }

    /**
     * 渠道目录树
     * GET /storage/folders?channel_id=1
     */
    public function index(Request $request): Response
    {
        $channelId = (int) $request->get('channel_id', 0);
        if ($channelId <= 0) {
            return $this->error('channel_id 必填');
        }

        return $this->success($this->folders->tree($channelId));
    }

    /**
     * 新建目录
     * POST /storage/folders {channel_id, parent_id, name}
     */
    public function create(Request $request): Response
    {
        try {
            $folder = $this->folders->mkdir(
                (int) $request->post('channel_id', 0),
                $request->post('parent_id') !== null ? (int) $request->post('parent_id') : null,
                (string) $request->post('name', '')
            );

            return $this->success(['id' => $folder->id, 'path' => $folder->path]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 目录重命名（物理迁移：List 旧前缀 → copy → delete）
     * PUT /storage/folders/{id} {name}
     */
    public function rename(Request $request, int $id): Response
    {
        try {
            $folder = $this->folders->rename($id, (string) $request->post('name', ''));

            return $this->success(['id' => $folder->id, 'path' => $folder->path]);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 删除目录（recursive=1 递归清空，否则仅空目录）
     * DELETE /storage/folders/{id}?recursive=1
     */
    public function remove(Request $request, int $id): Response
    {
        try {
            $this->folders->delete($id, (int) $request->get('recursive', 0) === 1);

            return $this->success();
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 扫描收编：把渠道内未登记的对象按前缀收编入附件表
     * POST /storage/folders/scan {channel_id}
     */
    public function scan(Request $request): Response
    {
        try {
            $result = $this->folders->scanUnregistered((int) $request->post('channel_id', 0));

            return $this->success($result);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * 存量初始化：按附件现有 path 前缀惰性生成目录树并回填 folder_id（幂等）
     * POST /storage/folders/init {channel_id}
     */
    public function init(Request $request): Response
    {
        try {
            $result = $this->folders->initFromAttachments((int) $request->post('channel_id', 0));

            return $this->success($result);
        } catch (\Throwable $e) {
            return $this->error($e->getMessage());
        }
    }
}
