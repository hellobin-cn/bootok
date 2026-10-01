<?php

namespace app\process;

use support\Log;
use Workerman\Crontab\Crontab as CrontabTask;

/**
 * 计划任务进程（单例，count=1，不参与弹性伸缩）
 * 部署约束：只允许在常驻机（SERVER_ROLE 含 crontab）上启动，弹性镜像必须 SERVER_ROLE=client
 */
class Crontab
{
    public function onWorkerStart(): void
    {
        // 在此注册定时任务，例如：
        new CrontabTask('0 * * * *', function () {
            // 每小时整点：示例任务
            Log::info('crontab 示例任务执行');
        });
        new CrontabTask('0 3 * * *', function () {
            // 每日凌晨3点：示例任务
            Log::info('crontab 每日任务执行');
        });
    }
}
