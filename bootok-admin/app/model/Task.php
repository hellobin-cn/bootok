<?php

namespace app\model;

use app\attribute\AutoResource;
use app\process\TaskRegistry;
use app\ui\Number;
use app\ui\Radio;
use app\ui\Select;
use app\ui\Text;
use app\ui\Textarea;

/**
 * 动态计划任务（后台创建/编辑/启停，任务进程热重载）
 * action 只能引用 TaskRegistry::dynamic() 登记的动作键——后台编排现有能力，不执行任意代码
 * @property int $id
 * @property string $name
 * @property string $cron
 * @property string $action
 * @property int $status
 */
#[AutoResource(name: '动态任务', read: 'task.rows')]
class Task extends Model
{
    protected $table = 'sys_task';

    public function form(): array
    {
        $actions = [];
        foreach (TaskRegistry::dynamic() as $key => $item) {
            $actions[$key] = $item['title'];
        }
        return [
            Text::make('name', '任务名')->required()->max(50)->help('唯一，可用于手动触发'),
            Text::make('cron', 'Cron 表达式')->required()->default('*/5 * * * *')->help('五段式：分 时 日 月 周，如 */5 * * * *'),
            Select::make('action', '执行动作')->options($actions)->required()->help('动作由代码登记（TaskRegistry::dynamic），后台只做编排'),
            Textarea::make('remark', '备注'),
            Radio::make('status', '状态')->options([1 => '启用', 0 => '停用'])->default(1),
        ];
    }
}
