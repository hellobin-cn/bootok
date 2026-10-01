# 快速开始

## 环境要求

| 依赖 | 版本 | 说明 |
|---|---|---|
| PHP | >= 8.5 | 需启用 pdo_mysql、redis 扩展（Redis 走 softik 客户端亦可） |
| MySQL | 5.7+ / 8.0 | utf8mb4 |
| Redis | 5.0+ | **必需**：token 单点、登录防爆破依赖 |
| Node.js | >= 18 | 前端构建与文档 |

## 后端

```bash
cd bootok-admin

# 1. 安装依赖
composer install

# 2. 配置 .env（数据库、Redis、JWT 密钥）
#    参考默认 .env，生产环境见「部署」页检查单

# 3. 建库 + 导入表结构与种子数据
mysql -uroot -p < database/bootok_admin.sql

# 4. 启动
php windows.php          # Windows 开发
php start.php start -d   # Linux 守护进程
```

启动后监听两个端口：

- `http://127.0.0.1:8787` — 管理端 API（含 `/auto` 自动资源）
- `http://127.0.0.1:8788` — 用户端 API（`/client/v1/*`）

## 前端

```bash
cd bootok-admin-ui
npm install
npm run dev
```

打开 `http://localhost:5173`，开发模式下 `/api` 自动代理到 8787。

## 默认账号

| 账号 | 密码 | 说明 |
|---|---|---|
| `admin` | `admin123` | 超级管理员，全量权限 |
| `editor` | `editor123` | 演示受限账号：仅「内容管理 → 文章管理」，可新增/编辑，不可删除 |

登录后建议立即修改密码（导航栏头像 → 修改密码）。

## 验证安装

```bash
# 后端健康检查
curl http://127.0.0.1:8787/

# 用户端
curl http://127.0.0.1:8788/client/v1/ping

# 单元测试
cd bootok-admin && composer test
```

前端能正常登录并看到四个菜单（仪表盘/系统管理/权限管理/内容管理），即部署成功。

## 常见问题

**登录报「登录已失效」**：Redis 未启动或连接失败——token 单点记录存于 Redis。

**修改了表结构不生效**：模型列信息有进程级缓存，重启服务（开发模式 monitor 会自动 reload）。

**弹性镜像上的计划任务**：镜像必须以 `SERVER_ROLE=client` 启动，见[部署](/guide/deployment)。
