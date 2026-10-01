# bootok-admin

通用后台管理服务端，基于 [webman](https://www.workerman.net/doc/webman/)（PHP 8.5+，常驻进程）。

核心设计：**模型即资源**——一张表 + 一个带 `#[AutoResource]` 注解的模型，即获得路由、表单、校验挂载点与权限点的全部能力，无需编写控制器。

**单库多运行形态**：管理端（8787）与用户端（8788）是同一份代码的两个进程组，配合计划任务进程，通过 `SERVER_ROLE` 环境变量按部署单元启停，模型/枚举零冗余。

## 特性

- **注解驱动的自动资源**：`#[AutoResource]` 白名单式暴露，未标注的模型不产生任何接口；`only/except` 收窄动作；`read` 为敏感模型声明读权限点
- **安全内建**：登录防爆破（IP+用户名计数锁定）、CORS 白名单（CORS_ORIGINS）、上传扩展名白名单（拒绝svg）、is_super 变更仅限超管、统一异常出口（任何异常渲染为响应契约信封）
- **token 生命周期**：权限上下文实时反查（改组/撤超管/删号即时生效，60s 缓存）；token 临近过期自动滑动续期（`X-Renewed-Token` 响应头 + 60s 并发宽限集合），前端无感知。**单会话约束**：同一账号后登录会挤掉先登录（旧 token 仅保留 60s 宽限），多端并行或自动化测试请使用独立账号
- **存储驱动可切换**：`app/storage/`（local 内置 / oss 骨架），`STORAGE_DRIVER` 环境变量切换，弹性拓扑下 C 端图片访问接 OSS/CDN
- **单一资源控制器**：`/auto/{model}` 参数化路由恒定 8 条，与模型数量无关，无需空壳控制器
- **表单 DSL**：模型 `form()` 方法用面向对象字段类（`app/ui`）声明表单，序列化协议与前端 crud 组件一一对应
- **查询构造**：`field_like / field_in / field_between / field_gte / field_lte` 后缀操作符，`page_size / order_by / order_type` 分页排序参数
- **权限模型（purview）**：权限组存「前端路由 name + 按钮权限点」集合，一套数据同时驱动前端菜单过滤与后端写操作鉴权
- **中间件链**：Access（JWT + Redis 单点）→ Validate（约定验证器）→ OperationLog（写操作落库，敏感字段脱敏）→ Permission（purview 校验，读操作放行）
- **操作日志**：只记录写操作，直接落库，无队列依赖

## 快速开始

```bash
# 1. 配置 .env（数据库、Redis、JWT密钥）
# 2. 导入建表脚本与种子数据
mysql -uroot -p < database/bootok_admin.sql
# 或参考 runtime/import.php 的 PDO 导入方式
# 3. 启动（Windows）
php windows.php
# Linux
php start.php start
```

默认监听 `http://127.0.0.1:8787`。

- 超级管理员：`admin / admin123`
- 演示权限组账号：`editor / editor123`（仅内容管理 + 文章增改）

## 新增一个业务模块

以「文章」为例，全部工作就是建表 + 一个模型文件：

```php
#[AutoResource(name: '文章')]
class Article extends Model
{
    protected $table = 'article';
    protected $primaryKey = 'article_id';

    public function form(): array
    {
        return [
            Text::make('title', '标题')->required()->max(100),
            Image::make('cover', '封面图'),
            Editor::make('content', '内容'),
            Select::make('status', '状态')->options(ArticleStatus::class)->default(1),
            Number::make('sort', '排序')->default(0),
        ];
    }
}
```

立即获得：

| 能力 | 端点 |
|---|---|
| 新建 | `POST /auto/article` |
| 分页列表 | `POST /auto/article/rows?page=1&page_size=15` |
| 全量列表 | `GET /auto/article/all` |
| 表单协议 | `GET /auto/article/form` |
| 详情 | `GET /auto/article/{id}` |
| 编辑 | `PUT /auto/article/{id}` |
| 删除 | `DELETE /auto/article/{id}` |
| 删除附件 | `DELETE /auto/article/attachment/{field}/{id}` |

需要更多定制时按需渐进添加：

- **校验**：创建 `app/validate/Article.php`，scene 名 = action 名（create/edit），自动生效
- **保存钩子**：模型实现 `beforeSave()`（返回错误信息中断）/ `afterSaved()` / `afterDeleted()`
- **复杂接口**：继承 `ResourceController` 设置 `$modelClass`，走独立路由

## 命名规范

| 层 | 规范 | 示例 |
|---|---|---|
| PHP 变量/方法 | camelCase | `$adminId` |
| PHP 类名 | StudlyCase | `SystemConfig` |
| 数组 key / DB 字段/表名 | snake_case | `admin_id`、`sys_admin` |
| URL 路径 | kebab-case | `/auto/system-config/rows` |
| 权限点 | `{模型kebab路径}.{action}` | `article.create` |

前后端之间自动做驼峰⇄下划线转换（前端 `src/api/base.ts` / 后端按 snake 接收）。

## 权限体系（purview）

- `sys_group.purview` 存 JSON 数组：前端路由 name（如 `ContentArticle`）+ 按钮权限点（如 `article.create`）
- 登录后 `GET /user/info` 下发 `{admin_name, is_super, purview}`，前端据此过滤菜单/路由与按钮
- 后端 `Permission` 中间件对写操作校验 `模型.action` 是否在 purview 中（按组缓存，file cache）
- **读权限**：模型注解 `read: 'admin.rows'` 声明后，该模型的 rows/item/form/all 需要对应权限点（用于管理员/操作日志/系统配置等敏感模型）；未声明的模型任何登录管理员可读
- `is_super` 是超管等价物：模型层强制只有超管可以设置或变更该标志（拥有 admin.create 不等于能造超管）
- `admin_id=1` 或 `is_super=1` 全量放行
- 权限组编辑在前端完成（遍历前端路由表勾选），后端无需配置任何菜单地址

## 响应结构

```json
{ "code": "0", "data": {}, "message": "操作成功", "request_id": "uuid" }
```

`code` 为字符串枚举：`0` 成功、`-1` 失败、`4001` 登录失效、`4003` 没有权限、`4004` 资源不存在、`4005` 参数错误。

## 部署形态（单库多运行形态）

一个仓库，三种部署单元，靠 `SERVER_ROLE` 环境变量切换（`config/process.php`）：

| SERVER_ROLE | 启动的进程 | 部署位置 |
|---|---|---|
| `all`（默认） | admin(8787) + client(8788) + crontab + monitor | 常驻机 |
| `client` | 仅 client(8788) | **弹性伸缩组镜像**（无计划任务，克隆安全） |
| `crontab` | 仅计划任务 | 独立任务机（可选，主备时配 Redis 锁） |

```bash
# 常驻机（admin + client + 任务）
php start.php start -d
# 弹性镜像（写死在镜像启动命令里，防止误配）
SERVER_ROLE=client php start.php start -d
```

**端口门禁**：admin 与 client 进程组共享路由表，`AdminPortGuard`/`ClientPortGuard` 中间件（`Request::getLocalPort()`）保证 `/auto/*`、`/login` 等只响应 8787，`/client/*` 只响应 8788——弹性机即使带着全量代码，管理端接口面在其端口上是死的。建议弹性机组安全组只放行 8788，双保险。

**计划任务**：`app/process/Crontab.php`（workerman/crontab），count=1 单例，只随 `all`/`crontab` 角色启动，绝不进入弹性镜像——重复执行问题从部署形态上消除。

**用户端 API 纪律**（`config/route/client.php` + `app/controller/client/`，路径带版本号 `/client/v1/*`）：
1. 绝不使用 `/auto` 自动资源，接口手动定义
2. 字段显式白名单（`get([...])`），防止后台字段（content 草稿、内部标记等）泄漏
3. 业务过滤显式声明（如只返回 `status=1` 上架数据）

**测试**：`composer test` 运行 phpunit 单元测试（解析器/验证器/表单DSL/注解/枚举，不触库）。

**连接池容量公式**（弹性部署必算）：MySQL 总连接 ≈ 机器数 × 每机器进程数（admin 4 + client 8）× `config/database.php` 池 `max_connections`（默认 5）。弹性扩容前按 MySQL `max_connections` 倒推单机池大小，或接入数据库代理。

**.env 生产检查单**：`APP_DEBUG=false`、`CORS_ORIGINS=` 具体后台域名、`JWT_KEY` 换新随机值、数据库密码强密码。

## 目录结构

```
app/
├── attribute/AutoResource.php   # 自动资源注解
├── ui/                          # 表单DSL字段类（Text/Select/Image/Editor...）
├── controller/
│   ├── Controller.php           # 基类：请求上下文 + 统一响应
│   ├── ResourceController.php   # 单一资源控制器（/auto/{model}）
│   ├── AdminController.php      # 登录/登出/改密
│   ├── IndexController.php      # /user/info 权限集合下发
│   └── UploadController.php     # 本地上传
├── middleware/                  # Cross/Access/Validate/Permission/OperationLog
├── model/                       # Model基类 + 系统模型 + 业务模型
├── validate/                    # 轻量验证器（scene = action名）
├── service/                     # Token/Admin/Permission/ModelResolver
└── enum/                        # Code / LabeledEnum / 业务枚举
database/bootok_admin.sql        # 建表脚本 + 种子数据
```
