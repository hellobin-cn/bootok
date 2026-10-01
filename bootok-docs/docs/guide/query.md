# 查询协议

列表查询（`POST /auto/{model}/rows`）与全量查询（`GET /auto/{model}/all`）共用一套条件协议：**字段名 + 操作符后缀**。条件按表列白名单过滤——不存在的列直接忽略，无注入风险。

## 操作符后缀

| 后缀 | 语义 | 值形态 | 示例 |
|---|---|---|---|
| （无） | 等值 | 标量 | `status=1` |
| `_like` | 模糊（%值%） | 标量 | `title_like=boot` |
| `_in` | IN | 逗号分隔 | `status_in=1,2` |
| `_between` | 区间（闭） | 逗号分隔起止 | `created_at_between=2026-01-01,2026-09-01` |
| `_gte` | >= | 标量 | `sort_gte=10` |
| `_lte` | <= | 标量 | `price_lte=99.9` |
| `_ne` | <> | 标量 | `status_ne=0` |

空值（null / 空串）条件自动忽略。

## 分页与排序

| 参数 | 说明 | 默认 |
|---|---|---|
| `page` | 页码（rows） | 1 |
| `page_size` | 每页条数（1~100） | 15 |
| `order_by` | 排序列（必须为真实表列，否则回退主键） | 主键 |
| `order_type` | `asc` / `desc` | desc |

`all` 不分页，有 1000 条硬上限。

## 前端用法

crud 组件的 `filter` prop 即条件对象，**键用驼峰**（BaseApi 自动转下划线）：

```ts
const filter = computed(() => ({
  titleLike: query.value.title,        // -> title_like
  status: query.value.status,          // -> status
  createdBetween: range.value,         // -> created_between（数组或逗号串）
}));
```

手写查询（BaseApi）：

```ts
const api = new BaseApi('Article');
await api.getRows(1, { titleLike: 'boot', status: 1 }, 30);  // 第1页，每页30
await api.getAll({ statusGte: 1 });
```

## 响应结构

rows 返回 Laravel 分页器（前端已转驼峰）：

```json
{
  "code": "0",
  "data": {
    "current_page": 1,
    "data": [ { "articleId": 1, "title": "…", "createdAt": "2026-09-19 10:00:00" } ],
    "total": 2,
    "per_page": 15
  }
}
```
