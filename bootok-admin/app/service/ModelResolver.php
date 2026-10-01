<?php

namespace app\service;

use app\attribute\AutoResource;
use ReflectionClass;

/**
 * 模型解析器
 * 把路由中的kebab-case模型段解析为带AutoResource注解的模型类
 */
class ModelResolver
{
    /**
     * kebab-case路径段转StudlyCase类名，如 job-info -> app\model\JobInfo
     */
    public static function resolve(string $path): ?string
    {
        if (!preg_match('/^[a-z][a-z0-9\-]*$/', $path)) {
            return null;
        }
        $class = 'app\\model\\' . str_replace(' ', '', ucwords(str_replace('-', ' ', $path)));
        if (!class_exists($class)) {
            return null;
        }
        if (!(new ReflectionClass($class))->getAttributes(AutoResource::class)) {
            return null;
        }
        return $class;
    }

    /**
     * 取模型的注解实例
     */
    public static function attribute(string $class): ?AutoResource
    {
        $attributes = (new ReflectionClass($class))->getAttributes(AutoResource::class);
        return $attributes ? $attributes[0]->newInstance() : null;
    }
}
