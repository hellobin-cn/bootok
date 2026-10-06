<template>
  <a-card :bordered="false" title="部门管理">
    <template #extra>
      <a-space>
        <a-button size="small" type="primary" @click="openForm(null)" v-permission="'dept.create'">
          <template #icon><icon-plus /></template>
          新建部门
        </a-button>
        <a-button size="small" @click="refresh">
          <template #icon><icon-refresh /></template>
          刷新
        </a-button>
      </a-space>
    </template>

    <a-table
      :data="treeTable"
      :loading="loading"
      :pagination="false"
      row-key="id"
      :default-expand-all-rows="true"
    >
      <template #columns>
        <a-table-column title="部门名称" data-index="name" />
        <a-table-column title="路径" data-index="path" />
        <a-table-column title="人数" data-index="admins" :width="90" align="center" />
        <a-table-column title="操作" :width="260">
          <template #cell="{ record }">
            <a-space size="mini">
              <a-button size="mini" type="text" @click="openForm(null, record.id)" v-permission="'dept.create'">
                +子部门
              </a-button>
              <a-button size="mini" type="text" @click="openForm(record)" v-permission="'dept.edit'">重命名</a-button>
              <a-popconfirm content="确认删除该部门？" type="warning" @ok="remove(record)">
                <a-button size="mini" status="danger" type="text" v-permission="'dept.delete'">删除</a-button>
              </a-popconfirm>
            </a-space>
          </template>
        </a-table-column>
      </template>
    </a-table>
  </a-card>

  <!-- 新建/重命名弹窗 -->
  <a-modal
    v-model:visible="formVisible"
    :title="form.id ? '重命名部门' : '新建部门'"
    :width="420"
    :mask-closable="false"
    @before-ok="submitForm"
    :ok-loading="saving"
  >
    <a-form :model="form" label-align="right" auto-label-width>
      <a-form-item label="上级部门">
        <a-select v-model="form.parentId" placeholder="不选则为根部门" allow-clear :disabled="!!form.id">
          <a-option :value="0">根部门</a-option>
          <a-option v-for="d in flatOptions()" :key="d.id" :value="d.id">{{ d.label }}</a-option>
        </a-select>
      </a-form-item>
      <a-form-item label="部门名称" required>
        <a-input v-model="form.name" placeholder="如：销售部" @press-enter="() => submitForm()" />
      </a-form-item>
    </a-form>
  </a-modal>
</template>

<script lang="ts" setup>
  import { computed, onMounted, reactive, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import { getToken } from '@/utils/auth';

  const loading = ref(false);
  const depts = ref<any[]>([]);
  const formVisible = ref(false);
  const saving = ref(false);
  const form = reactive({ id: 0, parentId: 0 as number, name: '' });

  async function call(method: any, url: string, data?: any): Promise<any> {
    const res: any = await axios.request({
      baseURL: import.meta.env.VITE_API_BASE_URL as string,
      url,
      method,
      headers: { Authorization: `Bearer ${getToken() ?? ''}` },
      data,
    });
    if (res.code !== '0') {
      Message.error(res.message || '操作失败');
    }
    return res;
  }

  async function loadDepts() {
    const res: any = await call('GET', '/dept/all');
    if (res.code === '0') depts.value = res.data ?? [];
  }

  function refresh() {
    loadDepts();
  }

  // 扁平 → 树表（path 前缀天然分层，按 path 排序后顺序插入）
  const treeTable = computed(() => {
    const sorted = [...depts.value].sort((a, b) => (a.path > b.path ? 1 : -1));
    return sorted.map((d) => ({
      ...d,
      name: buildLabel(sorted, d.path),
      key: d.id,
    }));
  });

  function buildLabel(sorted: any[], path: string): string {
    const depth = path.split('/').length - 1;
    return depth > 0 ? '　'.repeat(depth) + (depts.value.find((d) => d.path === path)?.name ?? path) : path;
  }

  function flatOptions() {
    // 上级部门下拉：全量平铺（排除自己与自己的子树由后端 rename 不涉及；新建时全量可选）
    return [...depts.value]
      .sort((a, b) => (a.path > b.path ? 1 : -1))
      .map((d) => ({ id: d.id, label: d.name, path: d.path }));
  }

  function openForm(record?: any, parentId?: number) {
    form.id = record?.id ?? 0;
    form.name = record?.name ?? '';
    form.parentId = (parentId ?? 0) as number;
    formVisible.value = true;
  }

  async function submitForm(done?: (closed: boolean) => void) {
    const name = form.name.trim();
    if (!name) {
      Message.error('请填写部门名称');
      return;
    }
    saving.value = true;
    try {
      let res: any;
      if (form.id) {
        res = await call('PUT', `/dept/${form.id}`, { name });
      } else {
        res = await call('POST', '/dept', {
          parent_id: form.parentId ?? 0,
          name,
        });
      }
      if (res.code === '0') {
        Message.success(form.id ? '已重命名' : '部门已创建');
        formVisible.value = false;
        await loadDepts();
        done?.(true);
      }
    } catch (e: any) {
      Message.error(e?.message ?? '保存失败');
    } finally {
      saving.value = false;
    }
  }

  async function remove(record: any) {
    const res = await call('DELETE', `/dept/${record.id}`);
    if (res.code === '0') {
      Message.success('已删除');
      await loadDepts();
    }
  }

  onMounted(loadDepts);
</script>
