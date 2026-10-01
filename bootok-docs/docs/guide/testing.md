# 测试

## 运行

```bash
cd bootok-admin
composer test        # 等价于 vendor/bin/phpunit --colors=never
```

## 覆盖范围

单元测试**不触数据库、不启动 webman**，覆盖纯逻辑：

| 测试集 | 覆盖 |
|---|---|
| `ModelResolverTest` | kebab→模型解析、注解闸门、路径注入拒绝、only/except、read 权限点 |
| `ValidatorTest` | 验证器场景规则（login/updatePassword/create 密码策略、confirm、min）、触库的唯一性规则不在此覆盖 |
| `FieldTest` | 表单 DSL 序列化协议（字段/修饰方法/枚举选项/默认值省略） |
| `CodeTest` | 响应契约 code 枚举 |

新增纯逻辑类（如自定义解析器、规则方法）应同步补测试；触库逻辑走集成测试（当前为手工脚本，后续可引入 sqlite 内存库扩展）。

## 手工冒烟清单

改核心链路（中间件、ResourceController、权限）后建议过一遍：

1. 双账号登录（admin / editor）
2. editor：读文章 OK、读操作日志 4003、建文章 OK、删文章 4003
3. admin：任意模型增删改查、表单协议
4. 未注解模型访问 → 4004；未登录 → 4001；跨端口访问 → 4004
5. 连续错 5 次密码 → 锁定提示
