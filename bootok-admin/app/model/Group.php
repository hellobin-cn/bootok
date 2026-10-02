<?php

namespace app\model;

use app\attribute\AutoResource;
use app\service\admin\PermissionService;
use app\ui\Text;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * 权限组
 * purview 为前端路由name与按钮权限点的集合，如 ["System","SystemAdmin","article.create"]
 * @property int $group_id
 * @property string $group_name
 * @property array $purview
 */
#[AutoResource(name: '权限组')]
class Group extends Model
{
    protected $table = 'sys_group';
    protected $primaryKey = 'group_id';

    protected function purview(): Attribute
    {
        return new Attribute(
            get: fn($value) => $value ? (json_decode($value, true) ?? []) : [],
            set: fn($value) => $value ? json_encode(array_values($value), JSON_UNESCAPED_UNICODE) : '[]'
        );
    }

    public function afterSaved(): void
    {
        (new PermissionService())->refreshAll();
    }

    public function afterDeleted(): void
    {
        (new PermissionService())->refreshAll();
    }

    public function form(): array
    {
        return [
            Text::make('groupName', '名称')->required(),
        ];
    }
}
