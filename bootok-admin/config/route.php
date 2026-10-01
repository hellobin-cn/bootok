<?php

use Webman\Route;

// 路由按运行形态拆分：admin(8787) / client(8788)
// 进程组共享路由表，端口门禁中间件保证跨端口不可达
require_once __DIR__ . '/route/admin.php';
require_once __DIR__ . '/route/client.php';

// 关闭自动路由
Route::disableDefaultRoute();
