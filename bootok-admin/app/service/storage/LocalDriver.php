<?php

namespace app\service\storage;

use RuntimeException;
use Webman\File;

/**
 * 本地存储驱动
 *
 * 渠道配置 config：
 *   root       存储根目录，相对 base_path（默认 public）或绝对路径
 *   cdn_domain 可选，绑定 CDN 域名后 URL 走 CDN
 *   scheme     CDN 访问协议 http/https（默认 https）
 *
 * 根目录在 public/ 下时由 webman 静态服务直出；在外部时经 /storage/{path} 路由读取。
 */
class LocalDriver implements StorageInterface
{
    public function __construct(private readonly array $config = [])
    {
    }

    public function put(File $file, string $relativePath): void
    {
        $absolute = $this->absolute($relativePath, true);
        if (!$file->move($absolute)) {
            throw new RuntimeException("文件保存失败：{$relativePath}");
        }
    }

    public function putContent(string $relativePath, string $content): void
    {
        $absolute = $this->absolute($relativePath, true);
        if (@file_put_contents($absolute, $content) === false) {
            throw new RuntimeException("文件写入失败：{$relativePath}");
        }
    }

    public function get(string $relativePath): ?string
    {
        $absolute = $this->absolute($relativePath);
        if (!is_file($absolute)) {
            return null;
        }
        $content = @file_get_contents($absolute);

        return $content === false ? null : $content;
    }

    public function exists(string $relativePath): bool
    {
        return is_file($this->absolute($relativePath));
    }

    public function delete(string $relativePath): void
    {
        $absolute = $this->absolute($relativePath);
        if (is_file($absolute)) {
            @unlink($absolute);
        }
    }

    public function list(string $prefix = ""): array
    {
        $prefix = trim($prefix, '/');
        $base = $prefix === '' ? $this->root() : $this->absolute($prefix);
        if (!is_dir($base)) {
            return [];
        }
        $result = [];
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            if ($item->isDir() || $item->getFilename() === '.keep') {
                continue;
            }
            $rel = ltrim(str_replace('\\', '/', substr($item->getPathname(), strlen($base))), '/');
            $key = trim($prefix . '/' . $rel, '/');
            $result[$key] = [
                'size'  => $item->getSize(),
                'mtime' => $item->getMTime(),
            ];
        }
        ksort($result);

        return $result;
    }

    public function copy(string $from, string $to): void
    {
        $fromAbs = $this->absolute($from);
        if (!is_file($fromAbs)) {
            throw new RuntimeException("源对象不存在：{$from}");
        }
        $toAbs = $this->absolute($to, true);
        if (!@copy($fromAbs, $toAbs)) {
            throw new RuntimeException("复制失败：{$from} → {$to}");
        }
    }

    public function deletePrefix(string $prefix): void
    {
        $prefix = trim($prefix, '/');
        if ($prefix === '') {
            throw new RuntimeException('拒绝删除渠道根前缀');
        }
        $base = $this->absolute($prefix);
        if (!is_dir($base)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($base);
    }

    public function url(string $relativePath): string
    {
        $cdn = trim((string) ($this->config['cdn_domain'] ?? ''));
        if ($cdn !== '') {
            return $this->scheme() . '://' . rtrim($cdn, '/') . '/' . ltrim($relativePath, '/');
        }
        // URL 拼接不做文件存在性检查（历史行文件可能已被手动清理，列表不能因此 500）
        if ($this->inPublic()) {
            // public 内（含子目录）：站点相对路径，静态服务直出
            $rootRel = trim(str_replace('\\', '/', substr(str_replace('\\', '/', $this->root()), strlen(str_replace('\\', '/', public_path())))), '/');

            return '/' . trim($rootRel . '/' . ltrim($relativePath, '/'), '/');
        }

        // public 外：经 /storage 只读路由
        return '/storage/' . ltrim($relativePath, '/');
    }

    public function signedUrl(string $relativePath, int $expires = 3600): string
    {
        // 本地文件无签名语义（访问控制由 /storage 路由或静态服务决定）
        return $this->url($relativePath);
    }

    public function test(): array
    {
        $probe = 'bootok-probe/' . date('Ymd') . '_' . bin2hex(random_bytes(4)) . '.tmp';
        $content = 'bootok-storage-probe ' . date('Y-m-d H:i:s');
        try {
            $this->putContent($probe, $content);
            $read = $this->get($probe);
            if ($read !== $content) {
                return ['ok' => false, 'message' => '写入后读取内容不一致'];
            }
            $this->delete($probe);
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        return ['ok' => true, 'message' => '本地目录读写正常：' . $this->root()];
    }

    /**
     * 相对路径 → 绝对路径（归一化并校验未越出根目录）
     *
     * @param bool $create 目录不存在时创建（写场景）；读场景不创建
     */
    private function absolute(string $relativePath, bool $create = false): string
    {
        $root = $this->root();
        $path = $root . '/' . ltrim(str_replace('\\', '/', $relativePath), '/');
        $dir = dirname($path);
        if ($create) {
            if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new RuntimeException("目录不可创建：{$dir}");
            }
        }
        $realDir = realpath($dir);
        $realRoot = realpath($root);
        if ($realDir === false || $realRoot === false || !str_starts_with($realDir . DIRECTORY_SEPARATOR, $realRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException("非法路径：{$relativePath}");
        }

        return $realDir . DIRECTORY_SEPARATOR . basename($path);
    }

    private function root(): string
    {
        $root = trim((string) ($this->config['root'] ?? 'public'));
        if ($root === '') {
            $root = 'public';
        }
        $isAbsolute = str_starts_with($root, '/') || preg_match('/^[A-Za-z]:[\/\\\\]/', $root);

        return $isAbsolute ? $root : base_path($root);
    }

    private function inPublic(): bool
    {
        $realRoot = realpath($this->root());
        $realPublic = realpath(public_path());
        if ($realRoot === false || $realPublic === false) {
            return false;
        }

        return str_starts_with(str_replace('\\', '/', $realRoot . '/'), str_replace('\\', '/', $realPublic . '/'));
    }

    private function scheme(): string
    {
        return ($this->config['scheme'] ?? 'https') === 'http' ? 'http' : 'https';
    }
}
