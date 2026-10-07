<?php

namespace app\service\admin;

use app\service\ConfigService;
use Throwable;

/**
 * 密码策略：最小长度 + 字母数字混合（配置中心「安全」组，未开启时仅长度生效）
 * 返回空串表示通过，否则返回错误消息；校验的是明文密码
 */
class PasswordPolicy
{
    public static function validate(string $password): string
    {
        [$min, $strength] = self::policy();
        if (mb_strlen($password) < $min) {
            return "密码长度不能少于 {$min} 位";
        }
        if ($strength) {
            if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
                return '密码必须同时包含字母和数字';
            }
        }
        return '';
    }

    /**
     * 配置读取失败（如纯单测环境无 Redis/DB 上下文）时落回内置默认，策略永不阻塞业务
     */
    private static function policy(): array
    {
        try {
            return [
                max(1, (int) ConfigService::get('password_min_length', 6)),
                (bool) ConfigService::get('password_strength', false),
            ];
        } catch (Throwable) {
            return [6, false];
        }
    }
}
