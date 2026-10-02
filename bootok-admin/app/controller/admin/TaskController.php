<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\enum\Code;
use app\model\Task;
use app\model\TaskRun;
use app\process\TaskRegistry;
use support\Redis;
use support\Request;
use support\Response;

/**
 * 计划任务管理
 * list：代码任务 + 动态任务 + 最近执行；run：写入触发标记（任务进程每秒轮询消费）
 * 动态任务 CRUD 走 /auto/task（Task 模型），此处只读汇总与触发
 */
class TaskController extends Controller
{
    /** 手动触发标记 TTL：任务进程未消费前防堆积 */
    private const TRIGGER_TTL = 60;

    public function list(): Response
    {
        $recent = TaskRun::query()
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'name', 'trigger', 'status', 'duration_ms', 'error', 'created_at', 'finished_at'])
            ->toArray();

        // 动态任务（后台配置）
        $dynamicTasks = [];
        try {
            $dynamicTasks = Task::query()->orderByDesc('id')
                ->get(['id', 'name', 'cron', 'action', 'status'])
                ->toArray();
        } catch (\Throwable) {
            // sys_task 未建表时忽略
        }

        // 代码内置任务
        $staticTasks = [];
        foreach (TaskRegistry::all() as $name => $task) {
            $last = TaskRun::where('name', $name)->orderByDesc('id')->first(['status', 'created_at']);
            $staticTasks[] = [
                'name'        => $name,
                'cron'        => $task['cron'],
                'last_status' => $last?->status,
                'last_run_at' => $last?->created_at,
                'source'      => 'code',
            ];
        }
        return $this->success([
            'static'  => $staticTasks,
            'dynamic' => $dynamicTasks,
            'recent'  => $recent,
        ]);
    }

    /**
     * 手动触发（动态任务与代码任务均可；写入标记，任务进程每秒轮询消费）
     */
    public function run(Request $request): Response
    {
        $name = (string)$request->post('name', '');
        $isDynamic = false;
        try {
            $isDynamic = Task::where('name', $name)->where('status', 1)->exists();
        } catch (\Throwable) {
        }
        if (!$isDynamic && !TaskRegistry::has($name)) {
            return $this->error('任务不存在', Code::PARAM_VALIDATE->value);
        }
        // 写触发标记，任务进程（单例）每秒轮询消费，天然排队不并发
        Redis::setex(
            config('app.name') . ':task:trigger:' . $name,
            self::TRIGGER_TTL,
            (string)time()
        );
        return $this->success([], "已触发任务：{$name}");
    }
}
