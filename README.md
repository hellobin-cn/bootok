# Bootok Admin

> 通用后台管理脚手架：**模型即资源**——一张表 + 一个带注解的模型，即得 REST 端点、表单、校验挂载点与权限点。
> 服务端基于 [Webman](https://www.workerman.net/doc/webman/)（PHP 8.5+ 常驻进程），前端基于 [Arco Design Pro](https://vue-pro.arco.design/)（Vue 3 + TypeScript）。

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
![PHP 8.5+](https://img.shields.io/badge/PHP-8.5%2B-blue)
![Webman 2.x](https://img.shields.io/badge/Webman-2.x-green)
![Vue 3](https://img.shields.io/badge/Vue-3-brightgreen)

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
| [`bootok-admin`](bootok-admin) | 服务端框架层：自动资源、表单 DSL、认证与权限、SSL 证书自动轮换、存储渠道与文件管理器、邮件管理、操作日志 | PHP 8.5+ / Webman / MySQL / Redis |
| [`bootok-admin-ui`](bootok-admin-ui) | 管理端前端：网盘式文件管理器、通用 crud 组件（与表单协议一一对应）、动态菜单与权限过滤 | Vue 3 / TypeScript / Arco Design Vue / Vite |
| [`bootok-docs`](bootok-docs) | 项目文档（VitePress）：上手、表单 DSL、前端约定、部署、命名规范 | VitePress |

服务端领域服务按目录归位：`app/service/ssl`（证书签发/部署/续期）、`app/service/storage`（存储渠道驱动与文件目录）、`app/service/cloud`（云平台客户端与 CDN 部署策略）。

## 核心特性

- **注解驱动的自动资源**：`/auto/{model}` 参数化路由恒定 8 条，与模型数量无关；`only/except` 收窄动作
- **表单 DSL**：模型 `form()` 用面向对象字段类（`app/ui`）声明表单，序列化协议与前端 crud 组件严格对应
- **purview 权限**：`sys_group.purview` 一份 JSON 同时驱动前端菜单过滤/按钮显隐与后端写操作鉴权；权限组编辑页遍历前端路由表生成勾选树
- **token 生命周期**：权限上下文实时反查（改组/撤超管/删号即时生效）；临近过期自动滑动续期（`X-Renewed-Token` + 并发宽限），前端无感知
- **SSL 证书自动轮换**：内置 ACME v2 客户端（纯 PHP，DNS-01 验证），Let's Encrypt 一键签发；到期前 30 天自动续期，新证书自动部署到本机、远程主机（SSH 免密）与阿里云/腾讯云 CDN 的 HTTPS 配置——全程无人值守
- **文件管理器**：目录树与本地磁盘 / OSS 桶前缀完全对应；上传、重命名、移动、递归删除、图片预览、未登记对象扫描收编
- **存储渠道可切换**：本地 / 阿里云 OSS / 腾讯云 COS 三驱动（纯 PHP 签名，无 SDK），渠道后台可视化管理，支持绑定 CDN 域名与 HTTPS
- **邮件管理**：渠道（SMTP）/ 模板 / 发送记录三件套，支持模板预览与测试发送、失败重发
- **动态计划任务**：后台创建 cron 表达式，任务进程秒级热重载；手动立即执行；执行记录留痕
- **单库多运行形态**：管理端 / 用户端 / 计划任务是同一份代码的三个进程组，`SERVER_ROLE` 按部署单元启停，模型与领域逻辑零冗余，定时任务从部署形态上杜绝重复执行
- **安全内建**：登录防爆破、CORS 白名单、上传扩展名白名单（拒绝 svg）、is_super 变更仅限超管、统一异常响应契约
- **操作日志**：只记写操作、直接落库、敏感字段脱敏、无队列依赖

## 快速开始

```bash
# 服务端（bootok-admin）
composer install
cp .env.template .env      # 配置数据库、JWT 密钥（.env.template 有逐项注释）
mysql -uroot -p bootok < bootok-admin/database/bootok_admin.sql
cd bootok-admin
php windows.php            # Windows；Linux 用 php start.php start -d
# 默认 http://127.0.0.1:8787

# 前端（bootok-admin-ui）
cd bootok-admin-ui
npm install
npm run dev                # dev 代理已指向 8787
```

导入 SQL 后使用默认账号 `admin / admin123` 登录——**公网部署请第一时间修改密码**。

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

项目仍在演进中（v0.x）：核心链路（自动资源 / 表单 / 权限 / SSL 证书轮换 / 文件管理器 / 邮件）已在生产项目验证；周边能力（文档、国际化）持续补齐。欢迎 issue 与 PR。

## License

[MIT](LICENSE) © 2026
