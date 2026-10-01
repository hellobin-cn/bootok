# 权限体系（purview）

## 概念

权限数据**只有一份**：`sys_group.purview`（JSON 数组），元素分两类：

| 类型 | 示例 | 驱动什么 |
|---|---|---|
| 前端路由 name | `ContentArticle` | 菜单显示、路由访问 |
| 按钮权限点 | `article.create` | 按钮显隐、后端写操作 |
| 读权限点 | `admin.rows` | 敏感模型的读接口 |

一套数据同时驱动前端（菜单/按钮）与后端（接口鉴权），后台**不需要配置任何菜单地址**。

## 数据流

```
登录 → JWT（内嵌 adminId，不内嵌权限）
     → 每次请求 Access 中间件按 adminId 实时反查管理员（60s 缓存）
     → GET /user/info 下发 { admin_name, is_super, purview } 存入 user store
前端：usePermission().accessRouter 过滤菜单/路由；v-permission 控按钮
后端：Permission 中间件按 purview 缓存校验 mark
```

**实时性**：改组、撤销超管、删除账号后立即生效（缓存主动失效），无需重新登录。

## 权限点（mark）格式

```
{模型kebab路径}.{action}     例：article.create、system-config.delete
```

action 对照：`create`（POST /auto/{model}）、`edit`、`delete`、`deleteFile`、`rows`/`item`/`form`/`all`（读操作）。比对不区分大小写。

## 读权限

默认**读操作放行**（任何登录管理员可读带注解模型）——页面可见性由菜单控制，业务模型无需逐个配读权限。

敏感模型（日志、配置、账号）在注解上声明 `read` 权限点收紧：

```php
#[AutoResource(name: '操作日志', only: ['getRows'], read: 'operation-log.rows')]
```

读权限点同样在前端路由 `meta.perms` 中声明，权限组编辑页显示为「查看」勾选项。内置声明了读权限的模型：`admin`、`operation-log`、`system-config`。

## is_super 保护

`is_super` 是超管等价物。模型层强制：**只有超管本人可以设置或变更该标志**——即使某权限组拥有 `admin.create`，也无法制造出超管账号。`admin_id=1` 恒为超管。

## 权限组管理

后台「权限管理 → 权限组」：

- 编辑页遍历**前端路由表**生成三层勾选树：板块（一级路由）→ 菜单（子路由）→ 操作权限（`meta.perms`）
- 新增页面/权限点后**自动出现**在勾选范围，零配置
- 保存后组缓存自动刷新

## 纪律

路由 name 是权限契约的一部分：**重命名前端路由前，检查 `sys_group.purview` 中的引用**，否则该组对该页面的授权静默失效。

## 前端用法

```vue
<!-- 按钮权限点 -->
<a-button v-permission="'article.delete'">删除</a-button>

<!-- 手动判断（权限点或路由 name） -->
<script setup>
import usePermission from '@/hooks/permission';
const permission = usePermission();
if (permission.hasPermission('article.edit')) { /* ... */ }
if (permission.accessRouter(route)) { /* ... */ }
</script>
```

超管（`is_super=1` 或 `admin_id=1`）全量放行，不查 purview。
