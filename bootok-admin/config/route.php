<?php

use Webman\Route;

// 路由按运行形态拆分：admin(8787) / client(8788)
// 进程组共享路由表，端口门禁中间件保证跨端口不可达
require_once __DIR__ . '/route/admin.php';
require_once __DIR__ . '/route/client.php';

// 本地存储只读出口：存储根目录在 public 外时，附件 URL 走此路由
// 公共资产语义（双端口可达），文件名含随机串不可变，允许长缓存
Route::get('/storage/{path:.+}', function ($request, $path) {
    $path = urldecode($path);
    if (str_contains($path, '..')) {
        return response('not found', 404);
    }
    $content = support\Container::get(app\service\storage\ChannelManager::class)->default()->get($path);
    if ($content === null) {
        return response('not found', 404);
    }
    $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png'         => 'image/png',
        'gif'         => 'image/gif',
        'webp'        => 'image/webp',
        'svg'         => 'image/svg+xml',
        'pdf'         => 'application/pdf',
        'mp4'         => 'video/mp4',
        'mp3'         => 'audio/mpeg',
        'txt', 'csv', 'md' => 'text/plain; charset=utf-8',
        default       => 'application/octet-stream',
    };

    return response($content, 200, [
        'Content-Type'  => $mime,
        'Cache-Control' => 'public, max-age=31536000, immutable',
    ]);
});

// 关闭自动路由
Route::disableDefaultRoute();
