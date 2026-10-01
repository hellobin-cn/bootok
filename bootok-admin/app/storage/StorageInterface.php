<?php

namespace app\storage;

use Webman\File;

/**
 * 存储驱动接口
 * 上传文件的落点抽象：local（常驻机本地）/ oss（对象存储，弹性拓扑下C端可访问）
 */
interface StorageInterface
{
    /**
     * 保存上传文件
     * @param File $file 上传的文件对象
     * @param string $relativePath 相对路径，如 upload/202609/xxx.jpg
     * @return string 可公开访问的完整URL
     */
    public function put(File $file, string $relativePath): string;
}
