# Bootok Admin

> 通用后台管理脚手架：**模型即资源**——一张表 + 一个带注解的模型，即得 REST 端点、表单、校验挂载点与权限点。
> 服务端基于 [Webman](https://www.workerman.net/doc/webman/)（PHP 8.5+ 常驻进程），前端基于 [Arco Design Pro](https://vue-pro.arco.design/)（Vue 3 + TypeScript）。

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)

## 这是什么

每个项目都需要一个后台。传统做法里一个业务模块要写迁移、模型、控制器、路由、验证器、列表页、表单页——Bootok 把这一切压缩成**约定**：

```php
#[AutoResource(name: '文章')]
class Article extends Model
{
    public function form(): array
    {
        return [
            Text::make('title', '标题')->required()->max(100),
            Image::make('cover', '封面图'),
            Editor::make('content', '内容'),
        ];
    }
}
```

这一个文件立即获得 8 条 REST 端点、表单协议、验证器挂载点和 `article.create` 等权限点。未标注 `#[AutoResource]` 的模型不暴露任何接口（白名单闸门）；简单场景在模型上完成，复杂场景继承 `ResourceController` 走独立路由，升级路径渐进。

## 仓库结构

| 目录 | 说明 | 技术 |
|---|---|---|
| [`bootok-admin`](bootok-admin) | 服务端框架层：自动资源、表单 DSL、认证与权限、操作日志、存储驱动 | PHP 8.5+ / Webman / MySQL / Redis |
| [`bootok-admin-ui`](bootok-admin-ui) | 管理端前端：通用 crud 组件（与表单协议一一对应）、布局、登录、权限过滤 | Vue 3 / TypeScript / Arco Design Vue / Vite |
| [`bootok-docs`](bootok-docs) | 项目文档（VitePress）：上手、表单 DSL、前端约定、部署、命名规范 | VitePress |

## 核心特性

- **注解驱动的自动资源**：`/auto/{model}` 参数化路由恒定 8 条，与模型数量无关；`only/except` 收窄动作
- **表单 DSL**：模型 `form()` 用面向对象字段类（`app/ui`）声明表单，序列化协议与前端 crud 组件严格对应
- **purview 权限**：`sys_group.purview` 一份 JSON 同时驱动前端菜单过滤/按钮显隐与后端写操作鉴权；权限组编辑页遍历前端路由表生成勾选树
- **token 生命周期**：权限上下文实时反查（改组/撤超管/删号即时生效）；临近过期自动滑动续期（`X-Renewed-Token` + 并发宽限），前端无感知
- **安全内建**：登录防爆破、CORS 白名单、上传扩展名白名单（拒绝 svg）、is_super 变更仅限超管、统一异常响应契约
- **单库多运行形态**：管理端 / 用户端 / 计划任务是同一份代码的三个进程组，`SERVER_ROLE` 按部署单元启停，模型与领域逻辑零冗余，定时任务从部署形态上杜绝重复执行
- **操作日志**：只记写操作、直接落库、敏感字段脱敏、无队列依赖
- **存储驱动可切换**：local 内置 / OSS 骨架，`STORAGE_DRIVER` 环境变量切换

## 快速开始

```bash
# 服务端（bootok-admin）
composer install
cp .env.example .env      # 配置数据库、Redis、JWT 密钥
mysql -uroot -p < database/bootok_admin.sql
php windows.php           # Windows；Linux 用 php start.php start
# 默认 http://127.0.0.1:8787 ，超管 admin / admin123

# 前端（bootok-admin-ui）
pnpm install
pnpm dev                  # dev 代理已指向 8787
```

演示权限组账号：`editor / editor123`（仅内容管理 + 文章增改），可体验 purview 菜单过滤与按钮权限。

## 文档

完整文档在 [`bootok-docs`](bootok-docs)（VitePress），本地运行：

```bash
cd bootok-docs && pnpm install && pnpm dev
```

- 上手指南（getting-started）
- 表单 DSL（form-dsl）——字段类、栅格、与 crud 组件的协议
- 前端约定（frontend）——crud 组件、路由、权限过滤
- 部署（deployment）——进程组与 `SERVER_ROLE`
- 命名规范（naming）

## 使用方式

Bootok 定位为**脚手架**而非依赖库，推荐按"复制实例化"使用：把 `bootok-admin` 与 `bootok-admin-ui` 复制为业务项目的基础，替换品牌文案、保留框架层、按业务模型加 `#[AutoResource]` 滚动出模块。详见文档"上手指南"。

## 状态

项目仍在演进中（v0.x）：核心链路（自动资源 / 表单 / 权限 / 日志 / 上传）已在生产项目验证；周边能力（文档、测试、国际化）持续补齐。欢迎 issue 与 PR。

## License

[MIT](LICENSE) © 2026
