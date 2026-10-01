<?php

namespace app\model;

use app\attribute\AutoResource;
use app\ui\Number;
use app\ui\Text;
use app\ui\Textarea;

/**
 * @property int $config_id
 * @property string $name
 * @property string $title
 * @property string $value
 * @property int $sort
 */
#[AutoResource(name: '系统配置', read: 'system-config.rows')]
class SystemConfig extends Model
{
    protected $table = 'sys_config';
    protected $primaryKey = 'config_id';

    public function form(): array
    {
        return [
            Text::make('name', '配置键')->required()->help('程序内读取的键名，如 site_name'),
            Text::make('title', '配置名')->required(),
            Textarea::make('value', '配置值'),
            Number::make('sort', '排序')->default(0),
        ];
    }
}
