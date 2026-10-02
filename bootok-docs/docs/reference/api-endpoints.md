# 接口端点

统一响应契约：`{ code, data, message, request_id }`，`code` 为字符串：`0` 成功、`-1` 失败、`4001` 登录失效、`4003` 没有权限、`4004` 资源不存在、`4005` 参数错误。

## 管理端（8787）

### 自身端点

| 方法 | 路径 | 说明 |
|---|---|---|
| POST | `/login` | 登录 `{username, password}` → `{access_token, access_token_exp}`；防爆破锁定 |
| POST | `/logout` | 退出（销毁单点记录） |
| POST | `/password` | 改密 `{old_password, new_password, re_password}` |
| GET | `/user/info` | 当前管理员 `{admin_name, is_super, purview}` |
| GET | `/admin/online` | 在线会话列表（仅超管） |
| POST | `/admin/online/{id}/kick` | 强制下线（仅超管） |
| GET | `/config/settings` | 参数设置（分组+类型化值） |
| POST | `/config/settings` | 批量保存 `{items:{key:value}}`（需 `config.save`） |
| GET | `/task/list` | 任务注册表+最近执行 |
| POST | `/task/run` | 手动触发任务 `{name}`（需 `task.run`） |
| POST | `/upload` | 上传 `{file, remark?}` → `{file_url, remark}` |
| POST | `/upload/editor` | 编辑器上传（wangeditor 协议） |

### 自动资源（/auto/{model}）

8 条路由恒定，`{model}` 为 kebab-case 模型名，需模型标注 `#[AutoResource]`：

| 方法 | 路径 | action | 权限点 |
|---|---|---|---|
| POST | `/auto/{model}` | create | `{model}.create` |
| POST | `/auto/{model}/rows` | getRows | 默认放行¹ |
| GET | `/auto/{model}/all` | getAll | 默认放行¹ |
| GET | `/auto/{model}/form` | getForm | 默认放行¹ |
| GET | `/auto/{model}/{id}` | getItem | 默认放行¹ |
| PUT | `/auto/{model}/{id}` | edit | `{model}.edit` |
| DELETE | `/auto/{model}/{id}` | delete | `{model}.delete` |
| DELETE | `/auto/{model}/attachment/{field}/{id}` | deleteFile | `{model}.deletefile` |
| POST | `/auto/{model}/export` | export | `{model}.export` |
| POST | `/auto/{model}/batch-delete` | batchDelete | `{model}.delete`（复用） |
| POST | `/auto/{model}/trash` | trash | 默认放行¹（软删除模型） |
| PUT | `/auto/{model}/{id}/restore` | restore | `{model}.restore` |
| DELETE | `/auto/{model}/{id}/force` | forceDelete | `{model}.delete`（复用） |

¹ 注解声明 `read: '{model}.rows'` 时需要该权限点，见[权限体系](/guide/permission)。

查询条件、分页排序见[查询协议](/guide/query)。

### 中间件链

```
Cross（CORS白名单 + request-id）
→ AdminPortGuard（只响应 8787）
→ Access（JWT + Redis 单点 + 实时权限反查 + 滑动续期）
→ Validate（app\validate\{Model}，scene = action）
→ OperationLog（写操作落库，敏感字段脱敏）
→ Permission（purview 校验）
```

## 用户端（8788）

路径带版本号 `/client/v1/*`，只响应 client 进程端口：

| 方法 | 路径 | 说明 |
|---|---|---|
| GET | `/client/v1/ping` | 健康检查 |
| GET | `/client/v1/articles` | 示例：上架文章（字段白名单：article_id/title/cover/created_at） |

纪律：不用 `/auto`；字段显式白名单；业务过滤显式；用户鉴权中间件按业务在路由组内挂载。

## curl 快验

```bash
# 登录
TOKEN=$(curl -s -X POST http://127.0.0.1:8787/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"admin123"}' | php -r 'echo json_decode(stream_get_contents(STDIN))->data->access_token;')

# 列表
curl -s -X POST "http://127.0.0.1:8787/auto/article/rows?page=1&page_size=10" \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -d '{"title_like":"示例"}'

# 新建
curl -s -X POST http://127.0.0.1:8787/auto/article \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" \
  -d '{"title":"标题","status":1,"sort":10}'
```
