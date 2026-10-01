<?php

namespace app\enum;

/**
 * 枚举标签接口
 * 实现此接口的枚举可直接作为 Select/Radio/Checkbox 的选项来源
 */
interface LabeledEnum
{
    public function label(): string;
}
