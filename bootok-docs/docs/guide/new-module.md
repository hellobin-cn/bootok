# 新增业务模块

这是本脚手架最核心的工作流。以新增「商品 Product」为例，全流程四步。

## 1. 建表

命名遵循[命名规范](/guide/naming)（表 snake_case，主键 `{前缀}_id`，带 created_at/updated_at）：

```sql
CREATE TABLE `product` (
    `product_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(100) NOT NULL COMMENT '名称',
    `price`       DECIMAL(10,2) NOT NULL DEFAULT 0 COMMENT '价格',
    `cover`       VARCHAR(500) NOT NULL DEFAULT '' COMMENT '封面图',
    `detail`      MEDIUMTEXT COMMENT '详情',
    `status`      TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1上架 0下架',
    `sort`        INT NOT NULL DEFAULT 0,
    `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`product_id`)
) ENGINE = InnoDB COMMENT '商品';
```

## 2. 后端模型（一个文件）

`app/model/Product.php`：

```php
<?php

namespace app\model;

use app\attribute\AutoResource;
use app\enum\ProductStatus;   // 可选：见「表单 DSL」枚举选项
use app\ui\Image;
use app\ui\Number;
use app\ui\Select;
use app\ui\Text;

#[AutoResource(name: '商品')]
class Product extends Model
{
    protected $table = 'product';
    protected $primaryKey = 'product_id';

    public function form(): array
    {
        return [
            Text::make('name', '名称')->required()->max(100),
            Number::make('price', '价格')->min(0)->step(0.01),
            Image::make('cover', '封面图'),
            Editor::make('detail', '详情'),
            Select::make('status', '状态')->options([1 => '上架', 0 => '下架'])->default(1),
            Number::make('sort', '排序')->default(0),
        ];
    }
}
```

完成。此时以下能力**已经存在**：

| 能力 | 端点 |
|---|---|
| 新建 | `POST /auto/product` |
| 分页列表 | `POST /auto/product/rows?page=1&page_size=15` |
| 全量列表 | `GET /auto/product/all` |
| 表单协议 | `GET /auto/product/form` |
| 详情 | `GET /auto/product/{id}` |
| 编辑 | `PUT /auto/product/{id}` |
| 删除 | `DELETE /auto/product/{id}` |
| 删除附件 | `DELETE /auto/product/attachment/{field}/{id}` |

权限点 `product.create / product.edit / product.delete` 自动成为合法 mark；验证器挂载点 `app/validate/Product.php`（建了即生效）。

### 需要更多定制时（渐进式）

- **校验**：`app/validate/Product.php`，scene 名 = action 名：

```php
class Product extends Validate
{
    protected array $rule = [
        'name' => 'require|max:100|checkName',
    ];
    protected array $scene = [
        'create' => ['name'],
        'edit'   => ['name'],
    ];
    protected function checkName($value, $rule, array $data = []): bool|string
    {
        // $data['id'] 在编辑时由路由参数自动并入，可排除自身
        return \app\model\Product::where('name', $value)
            ->when($data['id'] ?? null, fn($q) => $q->where('product_id', '<>', $data['id']))
            ->exists() ? '名称已存在' : true;
    }
}
```

- **保存钩子**：模型实现 `beforeSave()`（返回错误字符串则中断）/ `afterSaved()` / `afterDeleted()`。
- **敏感模型收紧读权限**：`#[AutoResource(name: '商品', read: 'product.rows')]`。
- **复杂接口**：继承 `ResourceController` 设 `$modelClass`，在 `config/route/admin.php` 挂独立路由。

## 3. 前端页面（一个文件）

`src/views/content/product.vue`：

```vue
<template>
  <crud
    ref="crudRef"
    model-name="product"
    model-text="商品"
    key-name="productId"
    :columns="columns"
    :filter="filter"
  >
    <template #search>
      <a-card :bordered="false" style="margin-bottom: 12px">
        <a-input-search
          v-model="query.name"
          placeholder="搜索名称"
          style="width: 240px"
          allow-clear
          @search="() => crudRef.getItems(1)"
          @clear="() => crudRef.getItems(1)"
        />
      </a-card>
    </template>
    <template #status="{ record }">
      <a-tag v-if="record.status === 1" color="green">上架</a-tag>
      <a-tag v-else color="red">下架</a-tag>
    </template>
  </crud>
</template>

<script lang="ts" setup>
import { computed, ref } from 'vue';
import crud from '@/components/crud/index.vue';
import type { TableColumnData } from '@arco-design/web-vue';

const crudRef = ref();
const query = ref({ name: '' });
// BaseApi 自动驼峰转下划线：nameLike -> name_like
const filter = computed(() => ({ nameLike: query.value.name }));

const columns = ref<TableColumnData[]>([
  { title: '名称', dataIndex: 'name' },
  { title: '状态', slotName: 'status', width: 90, align: 'center' },
]);
</script>
```

弹窗表单**不用写**——渲染协议来自后端 `form()`。

## 4. 注册路由

`src/router/routes/modules/content.ts` 的 children 里加一项：

```ts
{
  path: 'product',
  name: 'ContentProduct',
  component: () => import('@/views/content/product.vue'),
  meta: {
    locale: 'menu.content.product',
    title: '商品管理',
    requiresAuth: true,
    // 该页面的操作权限点，权限组编辑页会自动列出
    perms: ['product.rows', 'product.create', 'product.edit', 'product.delete'],
  },
},
```

locale 文件 `src/locale/zh-CN.ts` 加 `'menu.content.product': '商品管理'`。

## 5. 授权

后台 → 权限管理 → 权限组 → 编辑：勾选「内容管理 → 商品管理」及其操作权限 → 保存。**保存即生效**（组缓存自动刷新，无需重新登录）。

## 检查清单

- [ ] 表有主键、created_at/updated_at
- [ ] 模型标注 `#[AutoResource]`，主键 `$primaryKey` 与表一致
- [ ] `form()` 字段与表列一致（field 用驼峰，转下划线后必须存在该列）
- [ ] 需要校验的字段建了验证器
- [ ] 前端页面 + 路由 + locale 三件套
- [ ] 路由 `meta.perms` 声明全部权限点
- [ ] 权限组里勾选授权
