<?php

namespace app\model;

use app\attribute\AutoResource;
use app\service\ConfigService;
use app\ui\Number;
use app\ui\Select;
use app\ui\Text;
use app\ui\Textarea;

/**
 * 系统配置（配置中心）
 * 读取一律走 ConfigService；本模型仅承载配置项管理与缓存失效
 * @property int $config_id
 * @property string $name
 * @property string $title
 * @property string $group
 * @property string $type
 * @property string $value
 * @property int $sort
 */
#[AutoResource(name: '系统配置', read: 'system-config.rows')]
class SystemConfig extends Model
{
    protected $table = 'sys_config';
    protected $primaryKey = 'config_id';

    public function afterSaved(): void
    {
        ConfigService::flush();
    }

    public function afterDeleted(): void
    {
        ConfigService::flush();
    }

    public function form(): array
    {
        return [
            Text::make('name', '配置键')->required()->help('程序内读取的键名，如 site_name'),
            Text::make('title', '配置名')->required(),
            Text::make('group', '分组')->default('site')->help('设置页按此分Tab，如 site/payment'),
            Select::make('type', '类型')->options([
                'text' => '文本',
                'int'  => '整数',
                'bool' => '开关',
                'json' => 'JSON',
            ])->default('text')->help('决定参数设置页的控件与读取时的类型转换'),
            Textarea::make('value', '配置值'),
            Number::make('sort', '排序')->default(0),
        ];
    }
}
