<?php

/**
 * 管理端路由（只响应 admin 进程端口）
 * 全部管理端接口在此定义：登录、自身端点、/auto 自动资源
 */

use app\controller\admin\AdminController;
use app\controller\admin\AttachmentController;
use app\controller\admin\BackupController;
use app\controller\admin\ConfigController;
use app\controller\admin\DictController;
use app\controller\admin\DeptController;
use app\controller\admin\DnsAccountController;
use app\controller\admin\FolderController;
use app\controller\admin\IndexController;
use app\controller\admin\ImAlertController;
use app\controller\admin\MailChannelController;
use app\controller\admin\MailLogController;
use app\controller\admin\MailTemplateController;
use app\controller\admin\MessageController;
use app\controller\admin\MonitorController;
use app\controller\admin\QueueController;
use app\controller\admin\ResourceController;
use app\controller\admin\SslDeployController;
use app\controller\admin\SslTaskController;
use app\controller\admin\StorageChannelController;
use app\controller\admin\TaskController;
use app\controller\admin\UploadController;
use app\controller\admin\UserPrefController;
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
    Route::post('/login/2fa', [AdminController::class, 'login2fa']);
    // 健康检查（免认证，负载均衡/监控探针用）：DB/Redis/队列与任务心跳
    Route::get('/health', [MonitorController::class, 'health']);

    Route::group('', function () {

        // 自身相关端点，登录即可访问，不参与权限点校验
        Route::post('/logout', [AdminController::class, 'logout']);
        Route::post('/password', [AdminController::class, 'updatePassword']);
        // 个人界面偏好（页面配置/主题/语言，登录即可）
        Route::get('/user/pref', [UserPrefController::class, 'get']);
        Route::put('/user/pref', [UserPrefController::class, 'save']);
        Route::post('/admin/totp/setup', [AdminController::class, 'totpSetup']);
        Route::post('/admin/totp/enable', [AdminController::class, 'totpEnable']);
        Route::post('/admin/totp/disable', [AdminController::class, 'totpDisable']);
        Route::get('/user/info', [IndexController::class, 'getUserInfo']);
        Route::post('/upload', [UploadController::class, 'upload']);
        Route::post('/upload/editor', [UploadController::class, 'editorUpload']);
        // 在线会话（仅超管，控制器内守卫）
        Route::get('/admin/online', [AdminController::class, 'online']);
        Route::post('/admin/online/{adminId:\d+}/kick', [AdminController::class, 'kick']);
        // 参数设置（配置中心编辑侧）
        Route::get('/config/settings', [ConfigController::class, 'settings']);
        Route::post('/config/settings', [ConfigController::class, 'save']);
        // 计划任务管理（list 只读；run 需权限点 task.run）
        Route::get('/task/list', [TaskController::class, 'list']);
        Route::post('/task/run', [TaskController::class, 'run']);
        // SSL 证书签发（同步阻塞 60-120 秒，前端转圈等待）
        Route::post('/ssl/order', [SslTaskController::class, 'order']);
        // 证书产物下载（type: fullchain / privkey）
        Route::get('/ssl/download/{id:\d+}/{type:[a-z]+}', [SslTaskController::class, 'download']);
        // SSL 部署目标：立即部署 / 连通性测试（CRUD 走 /auto/ssl-deploy）
        Route::post('/ssl/deploy/{id:\d+}/run', [SslDeployController::class, 'run']);
        Route::post('/ssl/deploy/{id:\d+}/test', [SslDeployController::class, 'test']);
        // DNS 凭据连通性测试（CRUD 走 /auto/dns-account）
        Route::post('/ssl/dns-account/{id:\d+}/test', [DnsAccountController::class, 'test']);
        // 存储渠道配置自检（CRUD 走 /auto/storage-channel）
        Route::post('/storage/channel/test', [StorageChannelController::class, 'testConfig']);
        Route::post('/storage/channel/{id:\d+}/test', [StorageChannelController::class, 'test']);
        // 邮件：渠道测试 / 模板预览与测试发送 / 发送记录重发（CRUD 走 /auto/mail-*）
        Route::post('/mail/channel/test', [MailChannelController::class, 'testConfig']);
        Route::post('/mail/channel/{id:\d+}/test', [MailChannelController::class, 'test']);
        Route::post('/mail/template/preview', [MailTemplateController::class, 'preview']);
        Route::post('/mail/template/{id:\d+}/send-test', [MailTemplateController::class, 'sendTest']);
        Route::post('/mail/log/{id:\d+}/resend', [MailLogController::class, 'resend']);
        // 附件统计（CRUD 走 /auto/attachment）
        Route::get('/attachment/stats', [AttachmentController::class, 'stats']);
        // 附件批量签名地址（私有读桶的缩略图/预览场景）
        Route::post('/attachment/signed-urls', [AttachmentController::class, 'signedUrls']);
        // 数据字典（类型/项 CRUD 走 /auto/dict-type、/auto/dict-item）
        Route::get('/dict/all', [DictController::class, 'all']);
        // 部门管理（数据权限的组织树）
        Route::get('/dept/all', [DeptController::class, 'all']);
        Route::post('/dept', [DeptController::class, 'create']);
        Route::put('/dept/{id:\d+}', [DeptController::class, 'rename']);
        Route::delete('/dept/{id:\d+}', [DeptController::class, 'remove']);
        // 站内消息（个人数据，登录即可，不过 purview）
        Route::get('/message/latest', [MessageController::class, 'latest']);
        Route::get('/message/rows', [MessageController::class, 'rows']);
        Route::post('/message/read', [MessageController::class, 'read']);
        Route::post('/message/read-all', [MessageController::class, 'readAll']);
        Route::delete('/message/{id:\d+}', [MessageController::class, 'remove']);
        // IM 告警通道测试（配置在参数设置-告警）
        Route::post('/im/test', [ImAlertController::class, 'test']);
        // 队列观测（仅超管）
        Route::get('/queue/stats', [QueueController::class, 'stats']);
        Route::post('/queue/retry-failed', [QueueController::class, 'retryFailed']);
        // 系统监控（仅超管）
        Route::get('/monitor/system', [MonitorController::class, 'stats']);
        // 数据库备份（仅超管）
        Route::get('/backup/rows', [BackupController::class, 'rows']);
        Route::post('/backup/run', [BackupController::class, 'run']);
        Route::get('/backup/{id:\d+}/download', [BackupController::class, 'download']);
        Route::post('/backup/{id:\d+}/restore', [BackupController::class, 'restore']);
        Route::delete('/backup/{id:\d+}', [BackupController::class, 'remove']);
        // 文件管理器目录（与渠道内真实前缀对应）
        Route::get('/storage/folders', [FolderController::class, 'index']);
        Route::post('/storage/folders/scan', [FolderController::class, 'scan']);
        Route::post('/storage/folders/init', [FolderController::class, 'init']);
        Route::post('/storage/folders', [FolderController::class, 'create']);
        Route::put('/storage/folders/{id:\d+}', [FolderController::class, 'rename']);
        Route::delete('/storage/folders/{id:\d+}', [FolderController::class, 'remove']);

        // 自动资源路由，模型由 #[AutoResource] 注解在运行时闸门放行
        // 字面量段（rows/export/batch-delete/import/import-template/all/form/attachment）必须注册在 {id} 通配之前
        Route::group('/auto', function () {
            Route::post('/{model}', [ResourceController::class, 'create']);
            Route::post('/{model}/rows', [ResourceController::class, 'getRows']);
            Route::post('/{model}/export', [ResourceController::class, 'export']);
            Route::post('/{model}/batch-delete', [ResourceController::class, 'batchDelete']);
            Route::get('/{model}/import-template', [ResourceController::class, 'importTemplate']);
            Route::post('/{model}/import', [ResourceController::class, 'import']);
            Route::get('/{model}/all', [ResourceController::class, 'getAll']);
            Route::get('/{model}/form', [ResourceController::class, 'getForm']);
            Route::post('/{model}/trash', [ResourceController::class, 'trash']);
            Route::delete('/{model}/attachment/{field}/{id}', [ResourceController::class, 'deleteFile']);
            Route::get('/{model}/{id}', [ResourceController::class, 'getItem']);
            Route::put('/{model}/{id}', [ResourceController::class, 'edit']);
            Route::put('/{model}/{id}/restore', [ResourceController::class, 'restore']);
            Route::delete('/{model}/{id}', [ResourceController::class, 'delete']);
            Route::delete('/{model}/{id}/force', [ResourceController::class, 'forceDelete']);
        })->middleware([Permission::class]);

    })->middleware([Access::class, Validate::class, OperationLog::class]);

})->middleware([AdminPortGuard::class]);
