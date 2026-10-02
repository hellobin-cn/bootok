<?php

namespace app\service\admin;

use app\model\Group;
use support\Cache;

/**
 * 权限服务
 * 权限数据源为权限组的 purview 数组（前端路由name + 按钮权限点），按组缓存
 */
class PermissionService
{
    private function cacheKey(int $groupId): string
    {
        return 'group-purview-' . $groupId;
    }

    /**
     * 刷新单个组的权限缓存
     */
    public function refresh(int $groupId): array
    {
        $group = Group::find($groupId);
        $purview = $group ? $group->purview : [];
        Cache::set($this->cacheKey($groupId), json_encode($purview, JSON_UNESCAPED_UNICODE));
        return $purview;
    }

    /**
     * 刷新全部组缓存（组表或管理员变更时调用）
     */
    public function refreshAll(): void
    {
        Group::pluck('group_id')->each(fn($groupId) => $this->refresh($groupId));
    }

    /**
     * 取组的权限集合（带缓存）
     */
    public function purview(int $groupId): array
    {
        if (!$groupId) {
            return [];
        }
        $cached = Cache::get($this->cacheKey($groupId));
        if ($cached === null) {
            return $this->refresh($groupId);
        }
        return json_decode($cached, true) ?? [];
    }

    /**
     * 校验权限点
     */
    public function check(int $groupId, string $mark): bool
    {
        return in_array($mark, $this->purview($groupId));
    }
}
