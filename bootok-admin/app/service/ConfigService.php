<?php

namespace app\service;

use app\model\SystemConfig;
use support\Cache;

/**
 * 配置中心读取侧（全局服务）
 * 程序内一律通过本类读取配置，不直接查表：
 *   ConfigService::get('site_name', '默认值')   // 按声明类型 cast
 *   ConfigService::group('site')                // 整组读取
 * 全量缓存 + 模型保存/删除时自动失效（SystemConfig::afterSaved/afterDeleted）
 *
 * 分界纪律：环境差异与密钥类（DB/JWT/OSS）走 .env；业务运营可调项走本配置中心
 */
class ConfigService
{
    private const CACHE_KEY = 'config_all';

    /**
     * @return array{values: array<string, mixed>, meta: array<string, array{group: string, type: string, title: string}>}
     */
    private static function payload(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && isset($cached['values'], $cached['meta'])) {
            return $cached;
        }
        $values = [];
        $meta = [];
        foreach (SystemConfig::query()->get(['name', 'title', 'value', 'type', 'group']) as $row) {
            $values[$row->name] = self::cast($row->type, $row->value);
            $meta[$row->name] = ['group' => $row->group, 'type' => $row->type, 'title' => $row->title];
        }
        $payload = ['values' => $values, 'meta' => $meta];
        Cache::set(self::CACHE_KEY, $payload);
        return $payload;
    }

    /**
     * 全量配置值（name => 类型化值）
     */
    public static function all(): array
    {
        return self::payload()['values'];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::payload()['values'][$key] ?? $default;
    }

    /**
     * 取整组配置（name => 类型化值）
     */
    public static function group(string $name): array
    {
        $payload = self::payload();
        return array_filter(
            $payload['values'],
            fn($key) => ($payload['meta'][$key]['group'] ?? '') === $name,
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * 写入配置（触发缓存失效）
     */
    public static function set(string $key, mixed $value): bool
    {
        $row = SystemConfig::where('name', $key)->first();
        if (!$row) {
            return false;
        }
        $row->value = self::stringify($value);
        return (bool)$row->save();
    }

    public static function flush(): void
    {
        Cache::delete(self::CACHE_KEY);
    }

    /**
     * 按声明类型转换存储值
     */
    public static function cast(?string $type, mixed $value): mixed
    {
        return match ($type) {
            'int'   => (int)$value,
            'bool'  => in_array((string)$value, ['1', 'true', 'on', 'yes'], true),
            'json'  => json_decode((string)$value, true),
            default => (string)$value,
        };
    }

    /**
     * 写入库前的字符串化（bool 转 1/0，数组转 JSON）
     */
    public static function stringify(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE);
        }
        return (string)$value;
    }
}
