<?php

namespace app\storage;

use RuntimeException;
use Webman\File;

/**
 * OSS存储驱动骨架
 * 弹性伸缩 + C端访问图片时必须使用：图片落在对象存储/CDN，不依赖任何一台机器
 *
 * 接入步骤：
 * 1. composer require alibabacloud/oss-v2（或沿用项目惯用的 OSS SDK）
 * 2. .env 配置 STORAGE_DRIVER=oss 与 OSS_* 参数
 * 3. 实现 put()：上传到 bucket 并返回 CDN/bucket 公网 URL
 */
class OssDriver implements StorageInterface
{
    public function __construct(
        private string $bucket = '',
        private string $endpoint = '',
        private string $cdnDomain = '',
        private string $accessKeyId = '',
        private string $accessKeySecret = ''
    )
    {
    }

    public function put(File $file, string $relativePath): string
    {
        // TODO: $ossClient->putObject($this->bucket, $relativePath, $file->getContent());
        //       return ($this->cdnDomain ?: "https://{$this->bucket}.{$this->endpoint}") . '/' . $relativePath;
        throw new RuntimeException('OSS 存储驱动尚未接入，请在 app/storage/OssDriver.php 完成实现或使用 local 驱动');
    }
}
