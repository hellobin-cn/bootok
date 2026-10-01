# bootok-admin-ui

Bootok Admin 前端，基于 [Arco Design Pro](https://arco.design/vue/docs/pro/start)（Vue 3 + TypeScript + Vite + Pinia）。

核心设计：**菜单与路由全部前端静态定义**，权限走 purview 模型——权限组勾选前端路由树，一套「路由 name + 按钮权限点」集合同时驱动菜单过滤、路由守卫与按钮显隐；后端用同一份数据做接口鉴权。

## 快速开始

```bash
npm install
npm run dev        # http://localhost:5173，代理 /api -> 127.0.0.1:8787
npm run build      # 类型检查 + 生产构建
```

需要先启动后端 [bootok-admin](../bootok-admin)（webman，监听 8787）并导入 `database/bootok_admin.sql`。

- 超级管理员：`admin / admin123`
- 演示受限账号：`editor / editor123`（仅内容管理与文章增改）

## 架构

```
src/
├── api/
│   ├── interceptor.ts   # 全局axios：Bearer注入、统一响应契约、4001登出
│   ├── user.ts          # login/logout/userInfo/updatePassword
│   └── base.ts          # BaseApi：/auto/{model} 通用资源客户端，请求/响应自动驼峰⇄下划线
├── components/
│   ├── crud/index.vue   # 通用CRUD组件：表格+后端DSL驱动的表单弹窗+分页+上传
│   └── editor/editor.vue# wangeditor 富文本（带认证上传）
├── hooks/permission.ts  # purview权限判断：accessRouter / hasPermission
├── directive/permission # v-permission 按钮权限指令
├── router/routes/modules/  # 菜单路由静态定义（system/auth/content/dashboard）
├── store/modules/user   # 登录态 + purview + isSuper
└── views/
    ├── auth/            # 管理员、权限组列表、权限组编辑（勾选前端路由树）
    ├── system/          # 系统配置、操作日志
    ├── content/         # 文章管理（业务模块示例）
    └── dashboard/       # 工作台
```

## 新增一个业务页面

后端建表 + 写带 `#[AutoResource]` 注解的模型（见 bootok-admin README），前端只需两步：

1. 建页面 `src/views/content/foo.vue`：

```vue
<template>
  <crud model-name="foo" model-text="Foo" key-name="fooId" :columns="columns" />
</template>
<script lang="ts" setup>
import crud from '@/components/crud/index.vue';
import { ref } from 'vue';
import type { TableColumnData } from '@arco-design/web-vue';

const columns = ref<TableColumnData[]>([{ title: '名称', dataIndex: 'name' }]);
</script>
```

2. 在 `src/router/routes/modules/` 对应模块加路由，`meta.perms` 声明按钮权限点：

```ts
{
  path: 'foo',
  name: 'ContentFoo',
  component: () => import('@/views/content/foo.vue'),
  meta: {
    title: 'Foo管理',
    requiresAuth: true,
    perms: ['foo.create', 'foo.edit', 'foo.delete'],
  },
}
```

权限组编辑页会自动把新页面和权限点列入勾选范围，无需其他配置。

## 权限体系（purview）

- `sys_group.purview` 存 JSON 数组：路由 name（`ContentArticle`）+ 权限点（`article.create`）
- 登录后 `GET /user/info` 下发 `{admin_name, is_super, purview}` 存入 user store
- 菜单/路由：`usePermission().accessRouter(route)` 按 purview 过滤（超管全放行）
- 按钮：`v-permission="'article.create'"`（crud 组件的新建/编辑/删除已内置）
- 权限组编辑页（/auth/group）遍历**前端路由表**生成 板块/菜单/操作权限 三层勾选

## 查询协议（BaseApi）

列表页 filter 支持操作符后缀（自动驼峰转下划线）：

```ts
const filter = computed(() => ({
  titleLike: query.title,     // -> title_like 模糊
  status: query.status,       // -> status 等值
  createdAtBetween: range,    // -> created_at_between 区间
  sortGte: 10,                // -> sort_gte >=
}));
```

分页与排序：`page` / `page_size` / `order_by` / `order_type` 由 crud 组件与 BaseApi 自动携带。
