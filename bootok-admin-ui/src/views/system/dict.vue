<template>
  <div style="display: flex; gap: 12px; align-items: stretch; min-height: calc(100vh - 190px)">
    <!-- 左：字典类型列表 -->
    <a-card :bordered="false" style="flex: 0 0 320px" :body-style="{ padding: '8px' }">
      <template #title>
        <span class="card-title">字典类型</span>
      </template>
      <template #extra>
        <a-button size="mini" type="primary" @click="openType()" v-permission="'dict-type.create'">
          <template #icon><icon-plus /></template>
          新建
        </a-button>
      </template>

      <a-spin :loading="typeLoading" style="display: block; width: 100%">
        <div v-if="types.length === 0" class="type-empty">暂无字典类型</div>
        <div
          v-for="t in types"
          :key="t.id"
          class="type-item"
          :class="{ active: currentTypeId === t.id }"
          @click="selectType(t)"
        >
          <div class="type-main">
            <div class="type-name">{{ t.name }}</div>
            <div class="type-code">{{ t.code }}</div>
          </div>
          <div class="type-actions" @click.stop>
            <a-button size="mini" type="text" @click="openType(t)" v-permission="'dict-type.edit'">
              <icon-edit style="font-size: 14px" />
            </a-button>
            <a-popconfirm content="删除类型将同时删除其全部字典项，确认？" type="warning" @ok="deleteType(t)">
              <a-button size="mini" type="text" status="danger" v-permission="'dict-type.delete'">
                <icon-delete style="font-size: 14px" />
              </a-button>
            </a-popconfirm>
          </div>
        </div>
      </a-spin>
    </a-card>

    <!-- 右：字典项 -->
    <a-card :bordered="false" style="flex: 1 1 auto" :body-style="{ padding: '8px' }">
      <template #title>
        <span class="card-title">字典项{{ currentTypeName ? ` · ${currentTypeName}` : '' }}</span>
        <a-tag v-if="currentTypeCode" size="small" style="margin-left: 8px">{{ currentTypeCode }}</a-tag>
      </template>
      <template #extra>
        <a-button size="mini" type="primary" :disabled="!currentTypeId" @click="openItem()" v-permission="'dict-item.create'">
          <template #icon><icon-plus /></template>
          新建项
        </a-button>
      </template>

      <a-table
        :data="items"
        :loading="itemLoading"
        :pagination="false"
        size="small"
        :bordered="{ cell: false }"
      >
        <template #columns>
          <a-table-column title="显示文本" data-index="label" :width="180" />
          <a-table-column title="存储值">
            <template #cell="{ record }">
              <code class="value-code">{{ record.value }}</code>
            </template>
          </a-table-column>
          <a-table-column title="排序" data-index="sort" :width="70" align="center" />
          <a-table-column title="启用" :width="90" align="center">
            <template #cell="{ record }">
              <a-switch
                :model-value="!!record.status"
                size="small"
                @change="(v: any) => toggleItem(record, v)"
              />
            </template>
          </a-table-column>
          <a-table-column title="操作" :width="150" align="center">
            <template #cell="{ record }">
              <a-space size="mini">
                <a-button size="mini" type="text" @click="openItem(record)" v-permission="'dict-item.edit'">编辑</a-button>
                <a-popconfirm content="确认删除该字典项？" type="warning" @ok="deleteItem(record)">
                  <a-button size="mini" status="danger" type="text" v-permission="'dict-item.delete'">删除</a-button>
                </a-popconfirm>
              </a-space>
            </template>
          </a-table-column>
        </template>
      </a-table>
    </a-card>

    <!-- 类型弹窗 -->
    <a-modal
      v-model:visible="typeVisible"
      :title="typeForm.id ? '编辑字典类型' : '新建字典类型'"
      :width="440"
      :mask-closable="false"
      @before-ok="submitType"
      :ok-loading="typeSaving"
    >
      <a-form :model="typeForm" label-align="right" auto-label-width>
        <a-form-item label="名称" required>
          <a-input v-model="typeForm.name" placeholder="如：行业" />
        </a-form-item>
        <a-form-item label="编码" required help="小写字母开头的 [a-z0-9_]，模型 DictSelect 按此引用；创建后不可改">
          <a-input v-model="typeForm.code" :disabled="!!typeForm.id" placeholder="如：industry" />
        </a-form-item>
        <a-form-item label="备注">
          <a-input v-model="typeForm.remark" />
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- 字典项弹窗 -->
    <a-modal
      v-model:visible="itemVisible"
      :title="itemForm.id ? '编辑字典项' : '新建字典项'"
      :width="440"
      :mask-closable="false"
      @before-ok="submitItem"
      :ok-loading="itemSaving"
    >
      <a-form :model="itemForm" label-align="right" auto-label-width>
        <a-form-item label="显示文本" required>
          <a-input v-model="itemForm.label" placeholder="如：互联网" />
        </a-form-item>
        <a-form-item label="存储值" required help="创建后不可改（已存数据按此值关联）">
          <a-input v-model="itemForm.value" :disabled="!!itemForm.id" />
        </a-form-item>
        <a-form-item label="排序" help="升序">
          <a-input-number v-model="itemForm.sort" :min="0" style="width: 100%" />
        </a-form-item>
        <a-form-item label="状态">
          <a-switch v-model="itemForm.status" />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { onMounted, reactive, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import field from '@/utils/field';
  import { getToken } from '@/utils/auth';

  // 统一 raw 请求：全局拦截器已解包信封（res 即 {code, data, message}），失败路径以失败信封落定
  async function api(method: any, url: string, data?: any): Promise<any> {
    try {
      return await axios.request({
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        url,
        method,
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        data,
      });
    } catch (e: any) {
      Message.error(e?.message ?? '请求失败');
      return { code: '-1', data: null, message: e?.message ?? '' };
    }
  }

  // ---------- 字典类型 ----------
  const types = ref<any[]>([]);
  const typeLoading = ref(false);
  const currentTypeId = ref<number | null>(null);
  const currentTypeName = ref('');
  const currentTypeCode = ref('');

  const typeVisible = ref(false);
  const typeSaving = ref(false);
  const typeForm = reactive({ id: 0, code: '', name: '', remark: '' });

  async function loadTypes() {
    typeLoading.value = true;
    try {
      const res: any = await api('POST', '/auto/dict-type/rows?page=1&page_size=200', {});
      if (res.code === '0') {
        types.value = res.data?.data ?? [];
        // 当前选中类型被删时回退到第一个
        if (currentTypeId.value && !types.value.some((t) => t.id === currentTypeId.value)) {
          currentTypeId.value = null;
          currentTypeName.value = '';
          currentTypeCode.value = '';
          items.value = [];
        }
      }
    } finally {
      typeLoading.value = false;
    }
  }

  function selectType(record: any) {
    currentTypeId.value = record.id;
    currentTypeName.value = record.name;
    currentTypeCode.value = record.code;
    loadItems();
  }

  function openType(record?: any) {
    typeForm.id = record?.id ?? 0;
    typeForm.code = record?.code ?? '';
    typeForm.name = record?.name ?? '';
    typeForm.remark = record?.remark ?? '';
    typeVisible.value = true;
  }

  async function submitType(done?: (closed: boolean) => void) {
    if (!typeForm.name.trim() || !typeForm.code.trim()) {
      Message.error('请填写名称与编码');
      return;
    }
    typeSaving.value = true;
    try {
      // hump2underline 为原地转换无返回值（项目约定：先赋值再转换）
      const payload: any = { code: typeForm.code.trim(), name: typeForm.name.trim(), remark: typeForm.remark.trim() };
      field.hump2underline(payload);
      const res: any = await api(
        typeForm.id ? 'PUT' : 'POST',
        typeForm.id ? `/auto/dict-type/${typeForm.id}` : '/auto/dict-type',
        payload
      );
      if (res.code === '0') {
        Message.success(typeForm.id ? '已更新' : '已创建');
        typeVisible.value = false;
        await loadTypes();
        // 新建后自动选中并加载字典项
        if (!typeForm.id) {
          const fresh = types.value.find((t) => t.code === typeForm.code.trim());
          if (fresh) selectType(fresh);
        }
        done?.(true);
      }
    } catch (e: any) {
      Message.error(e?.message ?? '保存失败');
    } finally {
      typeSaving.value = false;
    }
  }

  async function deleteType(record: any) {
    const res = await api('DELETE', `/auto/dict-type/${record.id}`);
    if (res.code === '0') {
      Message.success('已删除');
      await loadTypes();
    }
  }

  // ---------- 字典项 ----------
  const items = ref<any[]>([]);
  const itemLoading = ref(false);
  const itemVisible = ref(false);
  const itemSaving = ref(false);
  const itemForm = reactive({ id: 0, label: '', value: '', sort: 0, status: true });

  async function loadItems() {
    if (!currentTypeId.value) return;
    itemLoading.value = true;
    try {
      const res: any = await api('POST', '/auto/dict-item/rows?page=1&page_size=500', { type_id: currentTypeId.value });
      if (res.code === '0') items.value = res.data?.data ?? [];
    } finally {
      itemLoading.value = false;
    }
  }

  function openItem(record?: any) {
    itemForm.id = record?.id ?? 0;
    itemForm.label = record?.label ?? '';
    itemForm.value = record?.value ?? '';
    itemForm.sort = record?.sort ?? 0;
    itemForm.status = record?.status ?? true;
    itemVisible.value = true;
  }

  async function submitItem(done?: (closed: boolean) => void) {
    if (!itemForm.label.trim() || !String(itemForm.value).trim()) {
      Message.error('请填写显示文本与存储值');
      return;
    }
    itemSaving.value = true;
    try {
      // hump2underline 为原地转换无返回值（项目约定：先赋值再转换）
      const payload: any = {
        type_id: currentTypeId.value,
        label: itemForm.label.trim(),
        value: String(itemForm.value).trim(),
        sort: itemForm.sort,
        status: itemForm.status ? 1 : 0,
      };
      field.hump2underline(payload);
      const res: any = await api(
        itemForm.id ? 'PUT' : 'POST',
        itemForm.id ? `/auto/dict-item/${itemForm.id}` : '/auto/dict-item',
        payload
      );
      if (res.code === '0') {
        Message.success(itemForm.id ? '已更新' : '已创建');
        itemVisible.value = false;
        await loadItems();
        done?.(true);
      }
    } catch (e: any) {
      Message.error(e?.message ?? '保存失败');
    } finally {
      itemSaving.value = false;
    }
  }

  async function toggleItem(record: any, enabled: boolean) {
    // hump2underline 为原地转换无返回值（项目约定：先赋值再转换）
    const payload: any = { status: enabled ? 1 : 0 };
    field.hump2underline(payload);
    const res = await api('PUT', `/auto/dict-item/${record.id}`, payload);
    if (res.code === '0') {
      record.status = enabled ? 1 : 0;
    }
  }

  async function deleteItem(record: any) {
    const res = await api('DELETE', `/auto/dict-item/${record.id}`);
    if (res.code === '0') {
      Message.success('已删除');
      await loadItems();
    }
  }

  onMounted(async () => {
    await loadTypes();
    if (types.value.length > 0) selectType(types.value[0]);
  });
</script>

<style scoped>
  .card-title {
    font-weight: 600;
  }

  /* 左：类型列表项 */
  .type-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 10px 10px 12px;
    border-radius: 6px;
    cursor: pointer;
    margin-bottom: 4px;
    border: 1px solid transparent;
  }
  .type-item:hover {
    background: var(--color-fill-2);
  }
  .type-item:hover .type-actions {
    visibility: visible;
  }
  .type-item.active {
    background: rgb(var(--blue-1));
    border-color: rgb(var(--blue-2));
  }
  .type-item.active .type-name {
    color: rgb(var(--blue-6));
    font-weight: 600;
  }
  .type-main {
    min-width: 0;
  }
  .type-name {
    font-size: 14px;
    color: var(--color-text-1);
    line-height: 1.4;
  }
  .type-code {
    font-size: 12px;
    color: var(--color-text-3);
    font-family: monospace;
  }
  .type-actions {
    visibility: hidden;
    display: flex;
    flex: 0 0 auto;
  }
  .type-empty {
    text-align: center;
    color: var(--color-text-3);
    padding: 32px 0;
    font-size: 13px;
  }

  .value-code {
    font-family: monospace;
    font-size: 13px;
    padding: 1px 6px;
    background: var(--color-fill-2);
    border-radius: 3px;
  }
</style>
