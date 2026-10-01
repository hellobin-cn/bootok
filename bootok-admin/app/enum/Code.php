<?php

namespace app\enum;

enum Code: string
{
    case SUCCESS = '0';
    case FAIL = '-1';
    case UNAUTHORIZED = '4001';
    case FORBIDDEN = '4003';
    case NOT_FOUND = '4004';
    case PARAM_VALIDATE = '4005';

    public function getDescription(): string
    {
        return match ($this) {
            self::SUCCESS        => 'success',
            self::FAIL           => '操作失败',
            self::UNAUTHORIZED   => '登录已失效',
            self::FORBIDDEN      => '没有权限',
            self::NOT_FOUND      => '资源不存在',
            self::PARAM_VALIDATE => '参数错误',
        };
    }
}
