<?php

namespace app\model;

use app\ui\Field;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Database\Eloquent\Builder;

/**
 * 模型基类
 * 提供列白名单过滤、条件构建、保存钩子和表单定义挂载点
 */
class Model extends \support\Model
{
    /**
     * 表列缓存（常驻进程内共享，避免每次请求查询schema）
     * @var array<string, array>
     */
    protected static array $columnCache = [];

    /**
     * 关闭批量赋值限制：写入白名单由 setData/filter 按表列控制
     */
    protected $guarded = [];

    protected function getCreatedAtAttribute(): string
    {
        if (($this->attributes['created_at'] ?? '') == '1000-01-01 00:00:00') {
            return '--/--';
        }
        try {
            return (new \DateTime($this->attributes['created_at']))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return '--/--';
        }
    }

    protected function getUpdatedAtAttribute(): string
    {
        if (($this->attributes['updated_at'] ?? '') == '1000-01-01 00:00:00') {
            return '--/--';
        }
        try {
            return (new \DateTime($this->attributes['updated_at']))->format('Y-m-d H:i:s');
        } catch (\Exception) {
            return '--/--';
        }
    }

    /**
     * 获取表列（带进程级缓存）
     */
    public function getColumns(): array
    {
        $table = $this->getTable();
        if (!isset(self::$columnCache[$table])) {
            self::$columnCache[$table] = Manager::schema()->getColumnListing($table) ?? [];
        }
        return self::$columnCache[$table];
    }

    /**
     * 按表列白名单赋值，天然过滤掉多余字段
     * 时间戳列由 Eloquent/数据库维护，外部输入不可覆写
     */
    public function setData($data): void
    {
        $columns = $this->getColumns();
        foreach ($data as $key => $value) {
            if (in_array($key, ['created_at', 'updated_at'], true)) {
                continue;
            }
            if (in_array($key, $columns)) {
                $this->{$key} = $value;
            }
        }
    }

    /**
     * 按表列白名单构建查询条件，返回已应用条件的查询构造器
     * 支持的操作符后缀：_like（模糊）、_in（逗号分隔）、_between（逗号分隔起止）、_gte、_lte、_ne，无后缀为等值
     */
    public function filter(array $data): Builder
    {
        $query = $this->newQuery();
        $columns = $this->getColumns();
        $operators = ['like', 'in', 'between', 'gte', 'lte', 'ne'];

        foreach ($data as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }
            $field = $key;
            $operator = '=';
            foreach ($operators as $suffix) {
                if (str_ends_with($key, '_' . $suffix)) {
                    $field = substr($key, 0, -strlen('_' . $suffix));
                    $operator = $suffix;
                    break;
                }
            }
            if (!in_array($field, $columns)) {
                continue;
            }
            switch ($operator) {
                case 'like':
                    $query->where($field, 'like', '%' . $value . '%');
                    break;
                case 'in':
                    $query->whereIn($field, is_array($value) ? $value : explode(',', (string)$value));
                    break;
                case 'between':
                    $range = is_array($value) ? $value : explode(',', (string)$value);
                    if (count($range) == 2) {
                        $query->whereBetween($field, $range);
                    }
                    break;
                case 'gte':
                    $query->where($field, '>=', $value);
                    break;
                case 'lte':
                    $query->where($field, '<=', $value);
                    break;
                case 'ne':
                    $query->where($field, '<>', $value);
                    break;
                default:
                    $query->where($field, '=', $value);
            }
        }
        return $query;
    }

    /**
     * 保存前钩子，返回错误信息则中断保存，返回空字符串继续
     * 通过 $this->exists 区分新建与编辑
     */
    public function beforeSave(): string
    {
        return '';
    }

    /**
     * 保存后钩子
     */
    public function afterSaved(): void
    {
    }

    /**
     * 删除后钩子
     */
    public function afterDeleted(): void
    {
    }

    /**
     * 表单定义，子类返回 app\ui\Field 字段对象数组
     * @return Field[]
     */
    public function form(): array
    {
        return [];
    }
}
