<?php

namespace app\storage;

use app\storage\LocalDriver;
use app\storage\OssDriver;

/**
 * 存储驱动工厂
 */
class Storage
{
    public static function driver(): StorageInterface
    {
        return match ((string)get_env('STORAGE_DRIVER', 'local')) {
            'oss'   => new OssDriver(
                bucket: (string)get_env('OSS_BUCKET', ''),
                endpoint: (string)get_env('OSS_ENDPOINT', ''),
                cdnDomain: (string)get_env('OSS_CDN_DOMAIN', ''),
                accessKeyId: (string)get_env('OSS_ACCESS_KEY_ID', ''),
                accessKeySecret: (string)get_env('OSS_ACCESS_KEY_SECRET', ''),
            ),
            default => new LocalDriver(),
        };
    }
}
