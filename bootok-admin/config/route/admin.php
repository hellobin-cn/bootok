<?php

/**
 * 管理端路由（只响应 admin 进程端口）
 * 全部管理端接口在此定义：登录、自身端点、/auto 自动资源
 */

use app\controller\AdminController;
use app\controller\IndexController;
use app\controller\ResourceController;
use app\controller\UploadController;
use app\middleware\Access;
use app\middleware\AdminPortGuard;
use app\middleware\OperationLog;
use app\middleware\Permission;
use app\middleware\Validate;
use Webman\Route;

Route::group('', function () {

    // 跨域预检
    Route::options('[{path:.+}]', function () {
        return response('');
    });

    Route::get('/', [IndexController::class, 'index']);
    Route::post('/login', [AdminController::class, 'login']);

    Route::group('', function () {

        // 自身相关端点，登录即可访问，不参与权限点校验
        Route::post('/logout', [AdminController::class, 'logout']);
        Route::post('/password', [AdminController::class, 'updatePassword']);
        Route::get('/user/info', [IndexController::class, 'getUserInfo']);
        Route::post('/upload', [UploadController::class, 'upload']);
        Route::post('/upload/editor', [UploadController::class, 'editorUpload']);

        // 自动资源路由，模型由 #[AutoResource] 注解在运行时闸门放行
        // 字面量段（rows/all/form/attachment）必须注册在 {id} 通配之前
        Route::group('/auto', function () {
            Route::post('/{model}', [ResourceController::class, 'create']);
            Route::post('/{model}/rows', [ResourceController::class, 'getRows']);
            Route::get('/{model}/all', [ResourceController::class, 'getAll']);
            Route::get('/{model}/form', [ResourceController::class, 'getForm']);
            Route::delete('/{model}/attachment/{field}/{id}', [ResourceController::class, 'deleteFile']);
            Route::get('/{model}/{id}', [ResourceController::class, 'getItem']);
            Route::put('/{model}/{id}', [ResourceController::class, 'edit']);
            Route::delete('/{model}/{id}', [ResourceController::class, 'delete']);
        })->middleware([Permission::class]);

    })->middleware([Access::class, Validate::class, OperationLog::class]);

})->middleware([AdminPortGuard::class]);
