<?php

/**
 * 用户端路由（只响应 client 进程端口）
 *
 * 纪律：
 * 1. 用户端绝不使用 /auto 自动资源，接口手动定义、字段显式白名单
 * 2. 用户鉴权中间件（如微信/手机号登录）按业务在此组内挂载
 */

use app\controller\client\ClientController;
use app\controller\client\WechatCallbackController;
use app\middleware\ClientPortGuard;
use Webman\Route;

Route::group('/client/v1', function () {

    // 健康检查
    Route::get('/ping', [ClientController::class, 'ping']);

    // 公众号服务器回调（接入验证 + 事件/消息，明文模式）
    Route::add(['GET', 'POST'], '/wechat/callback', [WechatCallbackController::class, 'callback']);

    // 示例：文章列表（显式字段白名单，仅上架数据）
    Route::get('/articles', [ClientController::class, 'articles']);

    // 用户鉴权组示例：
    // Route::group('', function () { ... })->middleware([ClientAccess::class]);

})->middleware([ClientPortGuard::class]);
