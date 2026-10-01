# 表单 DSL

表单由后端模型 `form()` 方法**面向对象声明**，字段类在 `app/ui/` 下。`ResourceController::getForm` 序列化为协议数组，前端 crud 组件按 `type` 渲染对应控件——**新增字段只改模型一处**。

## 字段类清单

| 类 | type | 控件 | 专属修饰 |
|---|---|---|---|
| `Text` | `text` | a-input | — |
| `Password` | `password` | a-input-password | 编辑态自动显示「留空则不修改」 |
| `Textarea` | `textarea` | a-textarea | `maxlength()` |
| `Number` | `number` | a-input-number | `min()` `max()` `step()` |
| `Select` | `select` | a-select | `options()` |
| `Radio` | `radio` | a-radio-group | `options()` |
| `Checkbox` | `checkbox` | a-checkbox-group | `options()` |
| `Date` | `date` | a-date-picker | — |
| `DateTime` | `date-time` | a-date-picker(show-time) | — |
| `Time` | `time` | a-time-picker | — |
| `File` | `file` | a-upload 图片卡 | `storage()` |
| `Image` | `file` | 同上（语义别名） | `storage()` 默认 image |
| `Editor` | `rich-text` | wangeditor | — |
| `Cascader` | `area-picker` | a-cascader | `options()` |

## 公共修饰方法

所有字段类继承 `app/ui/Field`：

```php
Text::make('title', '标题')
    ->required()                    // 前端即时校验 + 协议标记
    ->default('默认值')              // 新建时预填
    ->help('不超过50字')             // 表单项下方说明文字
    ->placeholder('请输入标题')
    ->prepend('http://')            // 前缀（input/number）
    ->append('.com')                // 后缀
    ->max(50);                      // 长度/数值上限
```

## 选项来源

`Select/Radio/Checkbox` 的 `options()` 接受两种形态：

```php
// 1. 关联数组
Select::make('status', '状态')->options([1 => '上架', 0 => '下架']);

// 2. BackedEnum 类名（实现 app\enum\LabeledEnum 接口取 label，否则取 case 名）
enum ArticleStatus: int implements LabeledEnum
{
    case Down = 0;
    case Up = 1;
    public function label(): string { return match($this) { self::Up => '上架', self::Down => '下架' }; }
}
Select::make('status', '状态')->options(ArticleStatus::class)->default(ArticleStatus::Up->value);
```

动态选项（如权限组下拉）直接查库：

```php
Select::make('groupId', '权限组')->options(Group::pluck('group_name', 'group_id')->toArray());
```

## 序列化协议

`GET /auto/{model}/form` 输出（与前端 `src/types/form-item.ts` 一一对应）：

```json
[
  { "field": "title",  "type": "text",   "label": "标题", "required": true, "max": 100 },
  { "field": "status", "type": "select", "label": "状态", "default": 1,
    "data": [ { "value": 0, "label": "下架" }, { "value": 1, "label": "上架" } ] },
  { "field": "content","type": "rich-text", "label": "内容" }
]
```

- `field` 用**驼峰**（请求时前端自动转下划线，必须对应真实表列）
- `default` 只在新建时应用
- 组件不认识的属性会被忽略——后端先行扩展、向后兼容

## 约定

- `form()` 返回 `Field[]`，模型不实现则返回空表单
- 老式数组 getForm 重写依旧有效（兼容过渡）
- 上传字段经 `/upload` 端点落盘，返回 URL 存入字段；编辑时删除附件走 `DELETE /auto/{model}/attachment/{field}/{id}`
