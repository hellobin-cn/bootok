<?php

namespace app\model;

use app\attribute\AutoResource;
use app\service\DictService;

/**
 * 字典类型（code 供模型 DictSelect 引用）
 *
 * @property int    $id
 * @property string $code
 * @property string $name
 * @property string $remark
 */
#[AutoResource(name: '字典类型', only: ['getRows', 'getItem', 'create', 'edit', 'delete'])]
class DictType extends Model
{
    protected $table = 'sys_dict_type';

    public function beforeSave(): string
    {
        $code = trim((string) $this->code);
        if ($code === '' || !preg_match('/^[a-z][a-z0-9_]*$/', $code)) {
            return '类型编码须为小写字母开头的 [a-z0-9_] 组合';
        }
        if (self::where('code', $code)->where('id', '!=', (int) ($this->id ?? 0))->exists()) {
            return "类型编码已存在：{$code}";
        }
        $this->code = $code;

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
