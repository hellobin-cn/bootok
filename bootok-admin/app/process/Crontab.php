<?php

namespace app\process;

use app\model\Task;
use app\model\TaskRun;
use app\service\MessageService;
use support\Log;
use Workerman\Crontab\Crontab as CrontabTask;
use Workerman\Timer;

/**
 * 计划任务进程（单例，count=1，不参与弹性伸缩）
 * 部署约束：只允许在常驻机（SERVER_ROLE 含 crontab）上启动，弹性镜像必须 SERVER_ROLE=api
 *
 * 任务两类：
 *  - 代码内置：TaskRegistry::all()
 *  - 后台动态：sys_task 表（后台创建/启停/改cron，本进程≤2秒热重载生效）
 * 自动获得：执行记录（sys_task_run）、手动触发（Redis 标记轮询）、心跳、配置变更热重载
 */
class Crontab
{
    private const HEARTBEAT_KEY = 'crontab:heartbeat';
    private const TRIGGER_PREFIX = 'task:trigger:';
    /** DB配置指纹键：变更即重载动态任务 */
    private const CONFIG_FINGERPRINT_KEY = 'task:dynamic-fingerprint';

    /** 已注册的动态任务：name => CrontabTask */
    private array $dynamicJobs = [];

    public function onWorkerStart(): void
    {
        // 心跳：外部拨测该键可发现任务进程假死
        Timer::add(60, function () {
            \support\Redis::set(config('app.name') . ':' . self::HEARTBEAT_KEY, time());
        });
        \support\Redis::set(config('app.name') . ':' . self::HEARTBEAT_KEY, time());

        // 代码内置任务
        foreach (TaskRegistry::all() as $name => $task) {
            new CrontabTask($task['cron'], function () use ($name) {
                $this->runTask($name, 'cron', $task['action']);
            });
        }

        // 后台动态任务（初始注册）
        $this->reloadDynamicTasks(true);

        // 手动触发轮询（1秒）+ 动态任务配置热重载（与轮询同频，指纹无变化零开销）
        Timer::add(1, function () {
            $this->pollTriggers();
            $this->reloadDynamicTasks();
        });
    }

    /**
     * 按 sys_task 注册/重载动态任务
     * @param bool $force 强制注册（启动时）
     */
    private function reloadDynamicTasks(bool $force = false): void
    {
        try {
            $tasks = Task::where('status', 1)->get(['id', 'name', 'cron', 'action'])->toArray();
        } catch (\Throwable $e) {
            // 表不存在（迁移未跑）等场景静默跳过
            return;
        }
        $fingerprint = md5(json_encode($tasks, JSON_UNESCAPED_UNICODE));
        $fingerprintKey = config('app.name') . ':' . self::CONFIG_FINGERPRINT_KEY;
        if (!$force && \support\Redis::get($fingerprintKey) === $fingerprint) {
            return; // 无变化，零开销
        }
        \support\Redis::set($fingerprintKey, $fingerprint);

        // 销毁旧的动态任务实例（workerman crontab 支持销毁；wakeup 由全局 tick 驱动）
        foreach ($this->dynamicJobs as $job) {
            $job->destroy();
        }
        $this->dynamicJobs = [];

        foreach ($tasks as $task) {
            if (!TaskRegistry::hasDynamic($task['action'])) {
                Log::warning("动态任务 {$task['name']} 的动作键 {$task['action']} 未登记，跳过");
                continue;
            }
            if (!self::validCron($task['cron'])) {
                Log::warning("动态任务 {$task['name']} cron 非法: {$task['cron']}");
                continue;
            }
            $name = $task['name'];
            $actionKey = $task['action'];
            $this->dynamicJobs[$name] = new CrontabTask($task['cron'], function () use ($name, $actionKey) {
                $this->runTask($name, 'cron', function () use ($actionKey) {
                    TaskRegistry::runDynamic($actionKey);
                });
            });
        }
        Log::info('动态任务已重载：' . count($this->dynamicJobs) . ' 个（指纹 ' . substr($fingerprint, 0, 8) . '）');
    }

    /** 五段 cron 基础校验（workerman crontab 构造时会再严格校验） */
    private static function validCron(string $cron): bool
    {
        $parts = explode(' ', trim($cron));
        return count($parts) === 5;
    }

    /**
     * 执行并记录（status: 2执行中→1成功/0失败）
     */
    private function runTask(string $name, string $trigger, ?callable $action = null): void
    {
        $action ??= TaskRegistry::all()[$name]['action'] ?? null;
        if (!$action) {
            return;
        }
        $started = microtime(true);
        $run = TaskRun::create([
            'name'    => $name,
            'trigger' => $trigger,
            'status'  => 2,
        ]);
        try {
            ($action)();
            $status = 1;
            $error = '';
        } catch (\Throwable $e) {
            $status = 0;
            $error = mb_substr($e->getMessage(), 0, 500);
            Log::error("任务 {$name} 失败: " . $e->getMessage());
            (new MessageService())->sendToSupers("任务「{$name}」执行失败", $error, MessageService::TYPE_TASK, '/system/task');
        }
        $run->status = $status;
        $run->duration_ms = (int)((microtime(true) - $started) * 1000);
        $run->error = $error;
        $run->finished_at = date('Y-m-d H:i:s');
        $run->save();
    }

    /**
     * 消费手动触发标记（每秒，幂等：先删后执行，避免重复）
     */
    private function pollTriggers(): void
    {
        $prefix = config('app.name') . ':' . self::TRIGGER_PREFIX;
        foreach ((array)\support\Redis::keys($prefix . '*') as $key) {
            \support\Redis::del($key);
            $name = substr($key, strlen($prefix));
            // 动态任务优先，其次代码内置任务
            $dynamic = Task::where('name', $name)->where('status', 1)->first(['action']);
            if ($dynamic && TaskRegistry::hasDynamic($dynamic->action)) {
                $this->runTask($name, 'manual', function () use ($dynamic) {
                    TaskRegistry::runDynamic($dynamic->action);
                });
            } elseif (TaskRegistry::has($name)) {
                $this->runTask($name, 'manual');
            }
        }
    }
}
