<?php

namespace app\model;

use app\attribute\AutoResource;
use app\service\admin\PermissionService;
use app\ui\Password;
use app\ui\Radio;
use app\ui\Select;
use app\ui\Text;
use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * @property int $admin_id
 * @property string $admin_name
 * @property string $admin_password
 * @property string $totp_secret
 * @property int $totp_enabled
 * @property int $group_id
 * @property int $is_super
 */
#[AutoResource(name: '管理员', except: ['deleteFile'], read: 'admin.rows')]
class Admin extends Model
{
    protected $table = 'sys_admin';
    protected $primaryKey = 'admin_id';
    // totp_secret 与密码同级敏感，列表/详情/导出一律不外发
    protected $hidden = ['admin_password', 'totp_secret'];

    protected function adminPassword(): Attribute
    {
        return new Attribute(
            set: fn($value) => $value === '' || $value === null ? $value : make_password($value)
        );
    }

    public function beforeSave(): string
    {
        // 编辑时密码留空表示不修改
        if ($this->exists && empty($this->admin_password)) {
            $this->offsetUnset('admin_password');
        }
        // is_super 是超管等价物：只有超管本人可以设置或变更
        $changing = !$this->exists || $this->is_super != $this->getOriginal('is_super');
        if ($changing) {
            $request = request();
            $actorSuper = $request && ($request->getCustomData('isSuper', 0) || $request->getCustomData('adminId', 0) == 1);
            if (!$actorSuper) {
                return '只有超级管理员可以设置超级管理员标志';
            }
        }
        return '';
    }

    public function afterSaved(): void
    {
        // 认证中间件实时反查的管理员上下文缓存立即失效
        \support\Cache::delete('admin-info-' . $this->admin_id);
        (new PermissionService())->refresh($this->group_id);
    }

    public function afterDeleted(): void
    {
        \support\Cache::delete('admin-info-' . $this->admin_id);
    }

    public function form(): array
    {
        return [
            Text::make('adminName', '用户名')->required(),
            Password::make('adminPassword', '密码')->help('编辑时留空表示不修改'),
            Select::make('groupId', '权限组')->options(Group::pluck('group_name', 'group_id')->toArray()),
            Select::make('deptId', '所属部门')->options((new \app\service\admin\DeptService())->selectOptions())->help('数据权限按部门维度生效'),
            Radio::make('isSuper', '超级管理员')->options([0 => '否', 1 => '是'])->default(0),
        ];
    }
}
