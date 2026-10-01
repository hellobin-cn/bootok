<?php

namespace app\enum;

/**
 * 文章状态
 */
enum ArticleStatus: int implements LabeledEnum
{
    case Down = 0;
    case Up = 1;

    public function label(): string
    {
        return match ($this) {
            self::Up   => '上架',
            self::Down => '下架',
        };
    }
}
