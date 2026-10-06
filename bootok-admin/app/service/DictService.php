<?php

namespace app\service;

use app\model\DictItem;
use app\model\DictType;

/**
 * 数据字典服务：表单 DSL（DictSelect）与表格标签翻译的统一出口
 *
 * 进程内缓存全量启用项；字典变更经模型钩子 flush 即时生效。
 */
class DictService
{
    /** @var array<string, array<int, array{value: mixed, label: string}>> code => options */
    private static ?array $cache = null;

    /**
     * 指定字典类型的启用选项（sort 升序）
     *
     * @return array<int, array{value: mixed, label: string}>
     */
    public static function options(string $code): array
    {
        if (self::$cache === null) {
            self::build();
        }

        return self::$cache[$code] ?? [];
    }

    /**
     * 选项显示文本翻译（表格列展示用；未匹配返回原文）
     */
    public static function label(string $code, mixed $value): ?string
    {
        foreach (self::options($code) as $option) {
            if ((string) $option['value'] === (string) $value) {
                return $option['label'];
            }
        }

        return null;
    }

    /**
     * 全部启用字典（type code => 选项集），供前端一次性拉取
     */
    public static function all(): array
    {
        if (self::$cache === null) {
            self::build();
        }

        return self::$cache;
    }

    public static function flush(): void
    {
        self::$cache = null;
    }

    private static function build(): void
    {
        $rows = DictItem::query()
            ->join('sys_dict_type', 'sys_dict_item.type_id', '=', 'sys_dict_type.id')
            ->where('sys_dict_item.status', 1)
            ->orderBy('sys_dict_type.id')
            ->orderBy('sys_dict_item.sort')
            ->orderBy('sys_dict_item.id')
            ->get([
                'sys_dict_type.code',
                'sys_dict_item.label',
                'sys_dict_item.value',
            ]);

        $cache = [];
        foreach ($rows as $row) {
            $cache[$row->code][] = ['value' => $row->value, 'label' => $row->label];
        }
        self::$cache = $cache;
    }
}
