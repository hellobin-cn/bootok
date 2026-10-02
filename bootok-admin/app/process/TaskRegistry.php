<?php

namespace app\process;

use support\Log;

/**
 * 任务注册表（共享定义）
 * 两类任务共存：
 *  - static()：代码内置任务（走代码内闭包）
 *  - dynamic()：后台创建任务的动作键登记表（sys_task.action 引用此处的键）
 * 新增任务：代码任务改 static()；后台任务先在 dynamic() 登记动作键，再在后台界面配置调度
 */
class TaskRegistry
{
    /** 动作键 => [说明, 处理器]；后台 sys_task.action 只能引用这里登记的键 */
    private static ?array $dynamicActions = null;

    public static function all(): array
    {
        return [
            '示例小时任务' => [
                'cron'   => '0 * * * *',
                'action' => function () {
                    Log::channel('json')->info('task.run', ['name' => '示例小时任务']);
                },
            ],
            '示例每日任务' => [
                'cron'   => '0 3 * * *',
                'action' => function () {
                    Log::info('crontab 示例每日任务执行');
                },
            ],
        ];
    }

    public static function has(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    /**
     * 后台动态任务可用的动作登记表
     * 键 => ['title' => 说明, 'action' => 处理器]
     * 安全边界：sys_task 只存动作键，处理器永远在代码里——后台编排能力，不执行用户代码
     */
    public static function dynamic(): array
    {
        if (self::$dynamicActions === null) {
            self::$dynamicActions = [
                'system.health_check' => [
                    'title'  => '系统健康巡检（检查Redis心跳与最近任务失败）',
                    'action' => function () {
                        // 示例动作：写一条结构化日志；真实业务可替换为告警/清理/对账等
                        Log::channel('json')->info('task.dynamic', [
                            'name'   => 'system.health_check',
                            'hb'     => \support\Redis::get(config('app.name') . ':crontab:heartbeat'),
                            'failed' => \app\model\TaskRun::where('status', 0)->where('created_at', '>', date('Y-m-d H:i:s', time() - 3600))->count(),
                        ]);
                    },
                ],
                'system.cleanup_tmp'  => [
                    'title'  => '临时文件清理（runtime/export 一小时前的导出文件）',
                    'action' => function () {
                        $files = glob(runtime_path('export') . '/*.xlsx') ?: [];
                        $cleaned = 0;
                        foreach ($files as $file) {
                            if (filemtime($file) < time() - 3600) {
                                @unlink($file);
                                $cleaned++;
                            }
                        }
                        Log::info('清理临时导出文件 ' . $cleaned . ' 个');
                    },
                ],
            ];
        }
        return self::$dynamicActions;
    }

    public static function hasDynamic(string $key): bool
    {
        return isset(self::dynamic()[$key]);
    }

    public static function runDynamic(string $key): void
    {
        $registered = self::dynamic()[$key] ?? null;
        if ($registered) {
            ($registered['action'])();
        }
    }
}
