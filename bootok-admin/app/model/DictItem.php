<?php

namespace app\model;

use app\attribute\AutoResource;
use app\service\DictService;

/**
 * 字典项（挂在字典类型下，sort 升序输出）
 *
 * @property int    $id
 * @property int    $type_id
 * @property string $label
 * @property string $value
 * @property int    $sort
 * @property int    $status
 */
#[AutoResource(name: '字典项', only: ['getRows', 'getItem', 'create', 'edit', 'delete'])]
class DictItem extends Model
{
    protected $table = 'sys_dict_item';

    protected $casts = [
        'status' => 'boolean',
    ];

    public function beforeSave(): string
    {
        if (trim((string) $this->label) === '' || trim((string) $this->value) === '') {
            return '显示文本与存储值不能为空';
        }
        if (self::where('type_id', $this->type_id)->where('value', $this->value)->where('id', '!=', (int) ($this->id ?? 0))->exists()) {
            return "存储值已存在：{$this->value}";
        }

        return '';
    }

    public function afterSaved(): void
    {
        DictService::flush();
    }

    public function afterDeleted(): void
    {
        DictService::flush();
    }
}
