<?php

namespace app\ui;

use app\service\DictService;

/**
 * 字典下拉字段：选项来自数据字典（后台「数据字典」页维护）
 *
 * 用法：DictSelect::make('industry', '行业')->dict('industry')
 * 序列化时服务端解析字典为普通 select 选项（data），前端 crud 组件零改动；
 * 字典项变更经缓存即时生效。
 */
class DictSelect extends OptionField
{
    public function type(): string
    {
        return 'select';
    }

    public function dict(string $code): static
    {
        $this->data = DictService::options($code);

        return $this;
    }
}
