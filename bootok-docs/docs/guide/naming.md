# 命名规范

每种边界各归其位，前后端之间自动转换：

| 层 | 规范 | 示例 |
|---|---|---|
| PHP 变量 / 方法 | camelCase | `$adminId`、`getUserInfo()` |
| PHP 类名 | StudlyCase | `SystemConfig`、`ResourceController` |
| 数组 key / DB 表名 / 字段 | snake_case | `admin_id`、`sys_group` |
| URL 路径 | kebab-case | `/auto/system-config/rows` |
| 前端 TS 变量 / 对象属性 | camelCase | `adminName` |
| 前端路由 name | 大驼峰 | `ContentArticle`、`AuthGroup` |
| 权限点 | `{模型kebab}.{action}` | `article.create`、`admin.rows` |

## 边界转换

前端 `src/api/base.ts` 与后端约定**自动互转**，组件内部永远用驼峰：

```
前端 { adminName: 'x' }  ──请求──▶  后端 { admin_name: 'x' }
后端 { admin_name: 'x' } ──响应──▶  前端 { adminName: 'x' }（递归）
```

因此：

- 模型 `form()` 的 `field` 写驼峰（`adminName`），转下划线后必须存在于表列
- crud 的 `columns.dataIndex`、`key-name` 写驼峰
- 验证器 `rule`/`scene` 的键写 snake（对齐请求体，验证发生在转换后的服务端）
- 表单字段与时间戳列（created_at/updated_at）不可被外部覆写，`setData` 自动剥离

## 为什么 URL 用 kebab

点分（`job.info`）与 snake（`job_info`）均合法，但 kebab 是公共 API 惯例且可读性最好；权限点直接跟随路径形态（`job-info.create`），与 mark 存储比对规则一致。
