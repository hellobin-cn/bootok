<?php
/**
 * 进程组定义
 *
 * 单库多运行形态：admin(管理端8787) / client(用户端8788) / crontab(计划任务，单例)
 * 通过 SERVER_ROLE 环境变量决定本机启动哪些进程组：
 *   - 常驻机（默认 all）：admin + client + crontab 全部启动
 *   - 弹性镜像（SERVER_ROLE=client）：只启动 client 进程组，镜像含全量代码但无计划任务
 *   - 独立任务机（SERVER_ROLE=crontab）：只启动计划任务
 */

use support\Log;
use support\Request;
use app\process\Crontab;
use app\process\Http;

global $argv;

$processes = [
    // 管理端HTTP：管理后台接口 + /auto 自动资源，运营使用，进程数无需大
    'admin' => [
        'handler' => Http::class,
        'listen' => getenv('ADMIN_LISTEN') ?: 'http://0.0.0.0:8787',
        'count' => 4,
        'user' => '',
        'group' => '',
        'reusePort' => false,
        'eventLoop' => '',
        'context' => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger' => Log::channel('default'),
            'appPath' => app_path(),
            'publicPath' => public_path()
        ]
    ],
    // 用户端HTTP：客户端API，承载弹性流量，进程数随流量放大
    'client' => [
        'handler' => Http::class,
        'listen' => getenv('CLIENT_LISTEN') ?: 'http://0.0.0.0:8788',
        'count' => 8,
        'user' => '',
        'group' => '',
        'reusePort' => true,
        'eventLoop' => '',
        'context' => [],
        'constructor' => [
            'requestClass' => Request::class,
            'logger' => Log::channel('default'),
            'appPath' => app_path(),
            'publicPath' => public_path()
        ]
    ],
    // 计划任务：不监听端口，count 固定1（单例语义，绝不参与弹性伸缩）
    'crontab' => [
        'handler' => Crontab::class,
        'count' => 1,
        'constructor' => []
    ],
    // SSL 签发后台进程：消费待签发任务（阻塞 60-120 秒），下单接口即时返回
    'ssl' => [
        'handler' => app\process\SslWorker::class,
        'count' => 1,
        'constructor' => []
    ],
    // 队列消费进程：即时+延迟任务，失败重试/死信，count 固定 1（串行语义）
    'queue' => [
        'handler' => app\process\QueueWorker::class,
        'count' => 1,
        'constructor' => []
    ],
    // File update detection and automatic reload
    'monitor' => [
        'handler' => app\process\Monitor::class,
        'reloadable' => false,
        'constructor' => [
            // Monitor these directories
            'monitorDir' => array_merge([
                app_path(),
                config_path(),
                base_path() . '/process',
                base_path() . '/support',
                base_path() . '/resource',
                base_path() . '/.env',
            ], glob(base_path() . '/plugin/*/app'), glob(base_path() . '/plugin/*/config'), glob(base_path() . '/plugin/*/api')),
            // Files with these suffixes will be monitored
            'monitorExtensions' => [
                'php', 'html', 'htm', 'env'
            ],
            'options' => [
                'enable_file_monitor' => !in_array('-d', $argv) && DIRECTORY_SEPARATOR === '/',
                'enable_memory_monitor' => DIRECTORY_SEPARATOR === '/',
            ]
        ]
    ]
];

// 按 SERVER_ROLE 过滤进程组（支持逗号分隔多角色，monitor 始终保留用于开发热重载）
$role = getenv('SERVER_ROLE') ?: 'all';
if ($role !== 'all') {
    $roles = array_map('trim', explode(',', $role));
    $processes = array_filter(
        $processes,
        fn($name) => in_array($name, $roles) || $name === 'monitor',
        ARRAY_FILTER_USE_KEY
    );
}

return $processes;
