<?php

namespace app\service\admin;

use app\model\ChangeLog;
use app\model\Model;
use support\Request;

/**
 * 字段级变更审计
 * 模型声明 public bool $auditChange = true 后由 ResourceController 自动调用
 */
class ChangeLogService
{
    /**
     * 记录一条变更
     * @param Model $model 已保存/已删除的模型实例
     * @param string $action created/updated/deleted
     * @param array|null $original 删除场景的原始属性
     */
    public static function record(Model $model, string $action, ?array $original = null): void
    {
        try {
            if (!($model->auditChange ?? false)) {
                return;
            }
            $changes = self::diff($model, $action, $original);
            if (!$changes) {
                return;
            }
            $request = request();
            ChangeLog::create([
                'table_name' => $model->getTable(),
                'pk_value'   => (string)($model->getKey() ?? ''),
                'action'     => $action,
                'changes'    => json_encode($changes, JSON_UNESCAPED_UNICODE),
                'admin_id'   => $request?->getCustomData('adminId', 0),
                'admin_name' => (string)$request?->getCustomData('adminName', ''),
            ]);
        } catch (\Throwable) {
            // 审计失败不影响业务
        }
    }

    /**
     * 字段 diff：updated 取 getChanges（from=原值 to=新值）；created 全新值；deleted 全旧值
     * 值统一字符串化，json diff 可读
     */
    private static function diff(Model $model, string $action, ?array $original): array
    {
        $changes = [];
        $normalize = fn($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : (string)$v;
        if ($action === 'updated') {
            foreach ($model->getChanges() as $field => $to) {
                $from = $model->getOriginal($field);
                $changes[$field] = ['from' => $normalize($from), 'to' => $normalize($to)];
            }
            return $changes;
        }
        $attrs = $action === 'deleted' ? ($original ?? $model->getAttributes()) : $model->getAttributes();
        foreach ($attrs as $field => $value) {
            $changes[$field] = [
                'from' => $action === 'deleted' ? $normalize($value) : null,
                'to'   => $action === 'deleted' ? null : $normalize($value),
            ];
        }
        return $changes;
    }
}
