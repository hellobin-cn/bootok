<?php

namespace app\validate;

/**
 * 动态计划任务验证器（/auto/task create/edit）
 */
class Task extends Validate
{
    protected array $rule = [
        'name'   => 'require|max:50',
        'cron'   => 'require|checkCron',
        'action' => 'require|checkAction',
    ];

    protected array $message = [
        'name.require'   => '请输入任务名',
        'name.max'       => '任务名不超过50个字符',
        'cron.require'   => '请输入 Cron 表达式',
        'action.require' => '请选择执行动作',
    ];

    protected array $scene = [
        'create' => ['name', 'cron', 'action'],
        'edit'   => ['name', 'cron', 'action'],
    ];

    /** 五段式 cron 基础校验（workerman crontab 运行时还会严格校验） */
    protected function checkCron($value, $rule, array $data = []): bool|string
    {
        $parts = preg_split('/\s+/', trim((string)$value));
        if (count($parts) !== 5) {
            return 'Cron 表达式须为五段式：分 时 日 月 周';
        }
        return true;
    }

    /** 动作键必须在 TaskRegistry::dynamic() 登记（安全边界） */
    protected function checkAction($value, $rule, array $data = []): bool|string
    {
        return \app\process\TaskRegistry::hasDynamic((string)$value)
            ? true
            : '执行动作未在代码中登记';
    }
}
