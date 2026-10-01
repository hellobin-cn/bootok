# 前端开发指南

前端基于 [Arco Design Pro](https://arco.design/vue/docs/pro/start)，菜单与路由**全部前端静态定义**，权限走 purview 模型。

## 目录速览

```
src/
├── api/
│   ├── interceptor.ts   # 全局axios：Bearer注入、统一响应契约、4001登出、续期头捕获
│   ├── user.ts          # login/logout/userInfo/updatePassword
│   └── base.ts          # BaseApi：/auto/{model} 通用客户端
├── components/
│   ├── crud/index.vue   # 通用CRUD组件
│   └── editor/editor.vue# wangeditor 富文本（带认证上传）
├── hooks/permission.ts  # purview判断：accessRouter / hasPermission
├── directive/permission/# v-permission 按钮权限指令
├── router/routes/modules/  # 菜单路由（静态）
├── store/modules/user   # 登录态 + purview + isSuper
└── views/
```

## crud 组件

| Prop | 说明 |
|---|---|
| `model-name` | 模型名（驼峰，自动转 kebab 路径） |
| `model-text` | 中文名（弹窗标题用） |
| `key-name` | 主键字段（驼峰） |
| `columns` | 列定义（TableColumnData[]），支持 `slotName` 自定义渲染 |
| `filter` | 查询条件（响应式对象，见[查询协议](/guide/query)） |
| `modal-width` / `show-id` / `show-create-time` / `show-update-time` | 展示开关 |
| `add-enable` / `edit-enable` / `delete-enable` / `action-enable` | 操作开关（新建/编辑/删除按钮自动挂 `v-permission`） |

| Slot | 说明 |
|---|---|
| `search` | 搜索区（置于列表卡上方） |
| `action` | 行内自定义按钮（追加在编辑/删除前） |
| `extra` / `right` | 卡片标题右侧 / 工具行右侧 |
| （任意具名插槽） | **自动透传给 a-table**，用于列渲染（如 `#status`、`#cover`） |

| Expose / Event | 说明 |
|---|---|
| `getItems(page?)` | 刷新列表（搜索后调 `crudRef.getItems(1)`） |
| `editItem(id?)` | 打开弹窗（表单协议由后端下发，新建自动应用 default） |
| `setTableData(...)` | 行内单元格回写 |
| `@save-success` / `@edit` | 保存成功 / 拦截编辑（权限组编辑页借此跳转自定义页） |

## 路由与菜单

菜单 = 路由表（`menuFromServer: false`），`meta` 约定：

```ts
{
  path: 'article',
  name: 'ContentArticle',        // 权限契约：purview 里存的就是它
  meta: {
    locale: 'menu.content.article',  // i18n 键（面包屑+菜单）
    title: '文章管理',               // 权限组编辑页显示名
    requiresAuth: true,              // true = 受 purview 控制
    icon: 'icon-bookmark',
    order: 3,                        // 菜单排序
    hideInMenu: true,                // 隐藏（如编辑页），配 activeMenu 高亮
    perms: ['article.create', 'article.edit', 'article.delete'],  // 操作权限点
  },
}
```

面包屑由布局层根据 `route.matched` 自动生成，页面无需引入。

## 权限

```vue
<a-button v-permission="'article.delete'">删除</a-button>
```

```ts
import usePermission from '@/hooks/permission';
const permission = usePermission();
permission.hasPermission('article.edit');      // 权限点
permission.accessRouter(toRoute);              // 路由可访问性
```

## 风格约定

- **无边框卡片**：全局 `.arco-card { border: none }`，白卡浮于浅灰内容背景（暗色主题自动适配），新页面直接用 `a-card` 即可
- **搜索区**：页面自管（crud 的 `#search` 插槽），样式 `style="margin-bottom: 12px"`
- 请求/响应自动驼峰⇄下划线转换，组件内部永远用驼峰

## 新增页面检查清单

1. `views/{板块}/{页面}.vue` 建 CRUD 页
2. `routes/modules/` 对应模块加路由（name / locale / title / perms）
3. locale 文件加 `menu.xxx` 键
4. 权限组里勾选授权
