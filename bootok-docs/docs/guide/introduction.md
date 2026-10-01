# 介绍

Bootok Admin 是一套**通用后台管理脚手架**，服务于「每个项目都需要一个后台」的场景：新增一个业务模块的成本被压到最低——一张表 + 一个模型文件 + 一个前端页面。

## 核心设计

### 模型即资源

传统后台开发一个模块要写：迁移、模型、控制器、路由、验证器、表单页、列表页。本项目中这一切由**约定**自动完成：

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

这一个文件立即获得 8 条 REST 端点、表单协议、验证器挂载点和 `article.create` 等权限点。未标注 `#[AutoResource]` 的模型**不暴露任何接口**（白名单闸门），简单场景在模型上完成，复杂场景继承 `ResourceController` 走独立路由——升级路径渐进。

### purview 权限

权限数据只有一份：`sys_group.purview`（JSON 数组），内容是**前端路由 name**（如 `ContentArticle`）和**按钮权限点**（如 `article.create`）的集合：

- 前端用它过滤菜单/路由、控制按钮显隐
- 后端用它校验写操作（读操作默认放行，敏感模型可通过 `read` 参数声明读权限点）
- 权限组编辑页遍历**前端路由表**生成勾选树——后台不用配置任何菜单地址，改路由权限跟着走

### 单库多运行形态

管理端、用户端、计划任务是同一份代码的三个**进程组**（不是三个项目）：

```
常驻机  SERVER_ROLE=all      → admin(8787) + client(8788) + crontab
弹性组  SERVER_ROLE=client   → 只启动 client 进程（镜像含全量代码，无计划任务）
任务机  SERVER_ROLE=crontab  → 只启动计划任务（可选）
```

模型、枚举、领域逻辑只有一份；计划任务单例（count=1）永不进入弹性镜像，「克隆机器导致定时任务重复执行」从部署形态上消除。

## 技术栈

| 端 | 技术 |
|---|---|
| 后端 | PHP 8.5+ / webman 2.2（workerman 5） / Laravel Eloquent 12 / Redis / MySQL |
| 前端 | Vue 3 / TypeScript / Vite / Arco Design Pro / Pinia |
| 文档 | VitePress |

## 项目结构

```
bootok/
├── bootok-admin/        后端（webman 常驻进程）
│   ├── app/
│   │   ├── attribute/   # AutoResource 注解
│   │   ├── ui/          # 表单 DSL 字段类
│   │   ├── controller/  # 基类 + 单一资源控制器 + client/
│   │   ├── middleware/  # Access/Validate/OperationLog/Permission/PortGuard
│   │   ├── model/       # 模型基类 + 业务模型
│   │   ├── validate/    # 轻量验证器（scene = action 名）
│   │   ├── storage/     # 存储驱动（local/oss）
│   │   └── process/     # 进程（Http/Crontab/Monitor）
│   ├── config/route/    # admin.php + client.php
│   ├── database/        # 建表脚本 + 种子数据
│   └── tests/           # phpunit 单元测试
├── bootok-admin-ui/     前端（Arco Design Pro）
│   └── src/
│       ├── api/         # interceptor + BaseApi（/auto 客户端）
│       ├── components/  # crud（DSL 驱动）/ editor
│       ├── hooks/       # usePermission（purview 判断）
│       ├── router/routes/modules/  # 菜单路由静态定义
│       └── views/       # auth/system/content/dashboard
└── bootok-docs/         本文档
```

## 适用场景与边界

适合：管理后台、内容管理、运营系统、配套的用户端 API。

不适合强求：本脚手架刻意不做的——多租户、微服务拆分、GraphQL。当业务出现不同的发布节奏或运行形态时，按「共享领域包 → monorepo」的路径演进，现有目录纪律保证拆分是机械搬运。
