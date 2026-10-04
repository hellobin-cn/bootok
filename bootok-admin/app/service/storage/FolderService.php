<?php

namespace app\service\storage;

use app\model\Attachment;
use app\model\Folder;
use app\model\StorageChannel;
use app\service\storage\ChannelFactory;
use RuntimeException;

/**
 * 目录服务：目录树与渠道内真实前缀完全对应
 *
 * 所有目录操作双写：物理（驱动 mkdir/copy/deletePrefix）+ 逻辑（sys_folder 行）。
 * 渠道根目录（path 为空）不落库，为每渠道的树根哨兵。
 */
class FolderService
{
    /**
     * 渠道的目录树（含哨兵根 node id=0）
     *
     * @return array<string, mixed>
     */
    public function tree(int $channelId): array
    {
        $rows = Folder::where('channel_id', $channelId)->orderBy('path')->get();
        $nodes = [['id' => 0, 'parentId' => null, 'name' => '/', 'path' => '']];
        foreach ($rows as $row) {
            $nodes[] = [
                'id'       => $row->id,
                'parentId' => $row->parent_id,
                'name'     => $row->name,
                'path'     => $row->path,
            ];
        }

        return ['channelId' => $channelId, 'nodes' => $nodes];
    }

    /**
     * 新建目录：物理（.keep 占位）+ 逻辑双写
     */
    public function mkdir(int $channelId, ?int $parentId, string $name): Folder
    {
        $name = $this->sanitizeName($name);
        if ($name === '') {
            throw new RuntimeException('目录名不能为空');
        }
        $parentPath = $this->parentPath($channelId, $parentId);
        $path = $this->normalizePath($parentPath . '/' . $name);
        if (Folder::where('channel_id', $channelId)->where('path', $path)->exists()) {
            throw new RuntimeException("目录已存在：{$path}");
        }

        // 物理占位（保证空目录在 OSS 上存在）
        $driver = $this->driver($channelId);
        $driver->putContent($path . '/.keep', 'bootok-folder-keep');

        $folder = new Folder([
            'channel_id' => $channelId,
            'parent_id'  => $parentId,
            'name'       => $name,
            'path'       => $path,
        ]);
        $folder->save();

        return $folder;
    }

    /**
     * 目录重命名：物理「List 旧前缀 → 逐对象 copy → delete 旧前缀」+ 更新本行与子树 path
     */
    public function rename(int $id, string $newName): Folder
    {
        $folder = Folder::find($id);
        if ($folder === null) {
            throw new RuntimeException('目录不存在');
        }
        $newName = $this->sanitizeName($newName);
        if ($newName === '' || $newName === $folder->name) {
            throw new RuntimeException('目录名无效');
        }

        $driver = $this->driver((int) $folder->channel_id);
        $oldPath = (string) $folder->path;
        $oldPrefix = $oldPath . '/';
        $newPath = $this->normalizePath(substr($oldPath, 0, -strlen($folder->name)) . $newName);
        $newPrefix = $newPath . '/';

        // 物理迁移：List 旧前缀 → copy 到新前缀 → delete 旧前缀
        $objects = $driver->list($oldPrefix);
        foreach (array_keys($objects) as $key) {
            $driver->copy($key, $newPrefix . substr($key, strlen($oldPrefix)));
        }
        $driver->deletePrefix($oldPrefix);

        // 逻辑更新：本行 + 子树 path 重建
        $folder->name = $newName;
        $folder->path = $newPath;
        $folder->save();
        foreach (Folder::where('channel_id', $folder->channel_id)->where('path', 'like', $oldPrefix . '%')->get() as $child) {
            $child->path = $newPrefix . substr((string) $child->path, strlen($oldPrefix));
            $child->save();
        }
        // 附件行的 path 同步迁移（保持逻辑=物理）
        foreach (Attachment::where('channel_id', $folder->channel_id)->where('path', 'like', '/' . $oldPrefix . '%')->get() as $att) {
            $att->path = '/' . $newPrefix . substr(ltrim((string) $att->path, '/'), strlen($oldPrefix));
            $att->save();
        }

        return $folder;
    }

    /**
     * 删除目录：recursive=true 时清空前缀下全部对象与子目录，否则仅允许空目录
     */
    public function delete(int $id, bool $recursive): void
    {
        $folder = Folder::find($id);
        if ($folder === null) {
            throw new RuntimeException('目录不存在');
        }
        $channelId = (int) $folder->channel_id;
        $prefix = (string) $folder->path;

        $hasChildren = Folder::where('channel_id', $channelId)->where('parent_id', $id)->exists();
        $hasFiles = Attachment::where('channel_id', $channelId)
            ->where('path', 'like', '/' . $prefix . '/%')->exists();
        if (!$recursive && ($hasChildren || $hasFiles)) {
            throw new RuntimeException('目录非空，请使用递归删除');
        }

        // 物理清空前缀
        $this->driver($channelId)->deletePrefix($prefix);

        // 逻辑清理：子树目录 + 目录下附件行（含存储对象）
        $childIds = [];
        foreach (Folder::where('channel_id', $channelId)->where('path', 'like', $prefix . '/%')->orderByDesc('path')->get() as $child) {
            $childIds[] = $child->id;
            $child->delete();
        }
        foreach (Attachment::where('channel_id', $channelId)->where('path', 'like', '/' . $prefix . '/%')->get() as $att) {
            $att->delete(); // afterDeleted 清理存储对象（已在 deletePrefix 清过则静默）
        }
        $folder->delete();
    }

    /**
     * 把某前缀下的对象收编为附件行（登记制）：未登记对象按前缀推断目录并落表
     *
     * @return array{registered: int}
     */
    public function scanUnregistered(int $channelId): array
    {
        $driver = $this->driver($channelId);
        $objects = $driver->list('');
        $known = Attachment::where('channel_id', $channelId)->pluck('path')->all();
        $known = array_map(fn ($p) => ltrim((string) $p, '/'), $known);

        // 现有目录行（含哨兵根）
        $folders = Folder::where('channel_id', $channelId)->get()->keyBy('path');
        $registered = 0;
        foreach ($objects as $path => $meta) {
            if (in_array($path, $known, true)) {
                continue;
            }
            // 按前缀推断/惰性创建所属目录
            $dir = dirname($path);
            $dir = $dir === '.' || $dir === '/' ? '' : $dir;
            $parentId = null;
            if ($dir !== '') {
                $segments = explode('/', $dir);
                $walk = '';
                $parentPath = null;
                foreach ($segments as $seg) {
                    $walk = $walk === '' ? $seg : $walk . '/' . $seg;
                    $folder = $folders[$walk] ?? null;
                    if ($folder === null) {
                        $folder = new Folder([
                            'channel_id' => $channelId,
                            'parent_id'  => $parentId,
                            'name'       => $seg,
                            'path'       => $walk,
                        ]);
                        $folder->save();
                        $folders[$walk] = $folder;
                    }
                    $parentId = $folder->id;
                }
                $parentPath = $dir;
            }
            $folderId = $dir === '' ? null : ($folders[$dir]->id ?? null);

            Attachment::create([
                'channel_id'    => $channelId,
                'folder_id'     => $folderId,
                'path'          => '/' . $path,
                'original_name' => mb_substr(basename($path), 0, 255),
                'size'          => $meta['size'],
                'ext'           => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            ]);
            $registered++;
        }

        return ['registered' => $registered];
    }

    /**
     * 存量初始化：按附件现有 path 前缀惰性生成目录树并回填 folder_id（幂等，可重复执行）
     *
     * @return array{folders: int, attached: int}
     */
    public function initFromAttachments(int $channelId): array
    {
        $foldersBefore = Folder::where('channel_id', $channelId)->count();
        $cache = [];
        foreach (Attachment::where('channel_id', $channelId)->orderBy('path')->get() as $att) {
            $path = ltrim((string) $att->path, '/');
            $dir = dirname($path);
            $folderId = null;
            if ($dir !== '.' && $dir !== '/') {
                $walk = '';
                $parentId = null;
                foreach (explode('/', $dir) as $seg) {
                    $walk = $walk === '' ? $seg : $walk . '/' . $seg;
                    $folder = $cache[$walk] ?? Folder::where('channel_id', $channelId)->where('path', $walk)->first();
                    if ($folder === null) {
                        $folder = new Folder([
                            'channel_id' => $channelId,
                            'parent_id'  => $parentId,
                            'name'       => $seg,
                            'path'       => $walk,
                        ]);
                        $folder->save();
                    }
                    $cache[$walk] = $folder;
                    $parentId = $folder->id;
                }
                $folderId = $parentId;
            }
            if ((int) $att->folder_id !== $folderId) {
                $att->folder_id = $folderId;
                $att->save();
            }
        }
        Folder::where('channel_id', $channelId)->orderBy('path')->get()->each(fn ($f) => $cache[$f->path] = $f);

        return ['folders' => Folder::where('channel_id', $channelId)->count() - $foldersBefore, 'attached' => Attachment::where('channel_id', $channelId)->whereNotNull('folder_id')->count()];
    }

    /**
     * 目录的物理路径（哨兵根为空串）
     */
    public function folderPath(int $channelId, ?int $folderId): string
    {
        if ($folderId === null || $folderId <= 0) {
            return '';
        }
        $folder = Folder::find($folderId);
        if ($folder === null || (int) $folder->channel_id !== $channelId) {
            throw new RuntimeException('目录不存在或不属于该渠道');
        }

        return (string) $folder->path;
    }

    private function parentPath(int $channelId, ?int $parentId): string
    {
        if ($parentId === null || $parentId <= 0) {
            return '';
        }
        $parent = Folder::find($parentId);
        if ($parent === null || (int) $parent->channel_id !== $channelId) {
            throw new RuntimeException('父目录不存在或不属于该渠道');
        }

        return (string) $parent->path;
    }

    private function normalizePath(string $path): string
    {
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $seg) {
            $seg = trim($seg);
            if ($seg === '' || $seg === '.') {
                continue;
            }
            if ($seg === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $seg;
        }

        return implode('/', $segments);
    }

    private function sanitizeName(string $name): string
    {
        return trim(str_replace(['/', '\\', "\0"], '', $name));
    }

    private function driver(int $channelId): \app\service\storage\StorageInterface
    {
        $channel = StorageChannel::find($channelId);
        if ($channel === null) {
            throw new RuntimeException('存储渠道不存在');
        }

        return ChannelFactory::build($channel);
    }
}
