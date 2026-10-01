# 部署

## 三种部署单元

一份代码，`SERVER_ROLE` 环境变量决定本机启动哪些进程组（`config/process.php`）：

| SERVER_ROLE | 启动进程 | 部署位置 |
|---|---|---|
| `all`（默认） | admin(8787) + client(8788) + crontab + monitor | 常驻机 |
| `client` | 仅 client(8788) | **弹性伸缩组镜像**（无计划任务，克隆安全） |
| `crontab` | 仅计划任务 | 独立任务机（可选） |

支持逗号分隔多角色，如 `SERVER_ROLE=web-admin,crontab`（若自定义拆分）。

```bash
# 常驻机
php start.php start -d

# 弹性镜像（写死在镜像的启动命令里，防止误配）
SERVER_ROLE=client php start.php start -d
```

## 弹性伸缩要点

- **计划任务单例**：`app/process/Crontab.php` 进程 count=1，只随 `all`/`crontab` 角色启动，绝不进弹性镜像——重复执行从部署形态上消除。注册新任务直接在该文件加 `new CrontabTask('0 3 * * *', fn() => ...)`
- **端口门禁**：两端口共享路由表，`AdminPortGuard`/`ClientPortGuard` 保证 `/auto/*`、`/login` 只响应 8787，`/client/*` 只响应 8788——弹性机即使带着全量代码，管理端接口面在其端口上是死的
- **双保险**：弹性机组安全组只放行 8788，管理端端口网络层不可达
- **镜像内容**：全量代码 + 启动命令带 `SERVER_ROLE=client`，一个镜像走天下
- **连接池容量公式**：MySQL 总连接 ≈ 机器数 × 进程数（admin 4 + client 8）× 池 `max_connections`（默认 5）。扩容前按 MySQL `max_connections` 倒推

## 存储

默认 `STORAGE_DRIVER=local`（文件落常驻机 `public/upload/`）。

弹性拓扑 + C 端要访问图片时切 OSS：

```env
STORAGE_DRIVER=oss
OSS_BUCKET=
OSS_ENDPOINT=
OSS_CDN_DOMAIN=
OSS_ACCESS_KEY_ID=
OSS_ACCESS_KEY_SECRET=
```

实现 `app/storage/OssDriver::put()`（骨架与接入步骤见文件注释）。

## 网络拓扑参考

```
                    ┌─ 常驻机 ROLE=all ── admin:8787 + client:8788 + crontab
SLB/nginx ──────────┤
  /client/* → 8788  └─ 弹性组 ROLE=client（N 台）─ client:8788
  /admin/*  → 常驻机 8787
```

## .env 生产检查单

| 项 | 生产值 |
|---|---|
| `APP_DEBUG` | **false**（异常信封隐藏堆栈细节） |
| `CORS_ORIGINS` | 后台具体域名，逗号分隔（如 `https://admin.example.com`） |
| `JWT_KEY` | 换新随机值（泄露即全站凭证失效风险） |
| `DB_PASSWORD` | 强密码 |
| `TOKEN_RENEW_THRESHOLD` | 默认 1800 即可 |

## 计划任务

```php
// app/process/Crontab.php — onWorkerStart 内注册
new CrontabTask('0 * * * *', function () { /* 每小时 */ });
new CrontabTask('0 3 * * *', function () { /* 每日 3 点 */ });
```

任务机无监护时会静默停摆，建议：任务执行时写 Redis 心跳时间戳 + 外部拨测告警。若任务必须与某组机器同生命周期，用 `Redis::set($lock, 1, ['nx', 'ex' => 300])` 竞选单例——但这是保险丝不是架构，资金类任务值得一台独立任务机。

## 观测

- `request_id` 贯穿响应体与异常信封，排查时按它过滤日志
- 操作日志（写操作）在后台「系统管理 → 操作日志」可查，参数自动脱敏
- 弹性机日志随销毁丢失，生产建议接入集中日志（filebeat/SLS）
