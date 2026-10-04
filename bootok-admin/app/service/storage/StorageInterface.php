<?php

namespace app\service\storage;

use Webman\File;

/**
 * 存储驱动契约
 *
 * 业务代码只依赖本接口（经容器注入，config/dependence.php 装配具体实现），
 * 不得在业务代码中写死任何具体驱动；驱动的选择与装配集中在存储渠道（ChannelManager/ChannelFactory）。
 */
interface StorageInterface
{
    /**
     * 保存上传文件到相对路径
     * @param File   $file        上传的文件对象
     * @param string $relativePath 相对路径，如 upload/202609/xxx.jpg
     */
    public function put(File $file, string $relativePath): void;

    /**
     * 写入原始内容（用于服务端生成的文件，如导出临时件）
     */
    public function putContent(string $relativePath, string $content): void;

    /**
     * 读取对象内容，不存在返回 null
     */
    public function get(string $relativePath): ?string;

    /**
     * 对象是否存在
     */
    public function exists(string $relativePath): bool;

    /**
     * 删除对象（不存在时静默成功）
     */
    public function delete(string $relativePath): void;

    /**
     * 公网访问 URL（渠道绑定 CDN 时返回 CDN 地址，scheme 由渠道配置决定 http/https）
     */
    public function url(string $relativePath): string;

    /**
     * 私有读取的签名 URL（公开读的驱动等同 url()）
     */
    public function signedUrl(string $relativePath, int $expires = 3600): string;

    /**
     * 配置自检：写读删一个探针对象，验证凭据、权限与网络连通
     *
     * @return array{ok: bool, message: string}
     */
    public function test(): array;

    /**
     * 列举前缀下全部对象（不含目录占位 .keep），按路径升序
     *
     * @param string $prefix 相对路径前缀（空 = 渠道根），如 "upload/202610"
     * @return array<string, array{size: int, mtime: int}> key 为相对路径
     */
    public function list(string $prefix = ""): array;

    /**
     * 复制对象（驱动按各自协议实现；源不存在抛异常）
     */
    public function copy(string $from, string $to): void;

    /**
     * 递归删除前缀下全部对象（含占位；不存在时静默成功）
     */
    public function deletePrefix(string $prefix): void;
}
