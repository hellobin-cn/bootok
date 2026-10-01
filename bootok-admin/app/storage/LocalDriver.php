<?php

namespace app\storage;

use Webman\File;

/**
 * 本地存储驱动：文件落在 public/ 下，由常驻机直接对外提供
 * 适用：单机部署、或图片仅管理端使用的场景
 */
class LocalDriver implements StorageInterface
{
    public function put(File $file, string $relativePath): string
    {
        $directory = dirname(public_path($relativePath));
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }
        $file->move(public_path($relativePath));
        return '/' . $relativePath;
    }
}
