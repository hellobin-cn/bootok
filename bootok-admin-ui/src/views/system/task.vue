<template>
  <a-card title="计划任务" :bordered="false">
    <a-alert style="margin-bottom: 16px" type="info">
      代码任务定义在 TaskRegistry（部署内生效）；动态任务在后台创建，任务进程
      ≤2 秒热重载生效。两类任务均可「立即执行」验证，全部执行留痕于下方记录。
    </a-alert>

    <!-- 动态任务（后台可管理） -->
    <a-card :bordered="false" title="动态任务（后台管理）" style="margin-bottom: 16px">
      <template #extra>
        <a-button type="primary" size="small" @click="openEdit()" v-permission="'task.create'">
          新建任务
        </a-button>
      </template>
      <a-table :data="dynamic" :loading="loading" :pagination="false">
        <template #columns>
          <a-table-column title="任务名" data-index="name" :width="180" />
          <a-table-column title="Cron" data-index="cron" :width="130" />
          <a-table-column title="动作" data-index="action" ellipsis />
          <a-table-column title="状态" :width="90" align="center">
            <template #cell="{ record }">
              <a-tag v-if="record.status === 1" color="green">启用</a-tag>
              <a-tag v-else color="gray">停用</a-tag>
            </template>
          </a-table-column>
          <a-table-column title="操作" :width="220">
            <template #cell="{ record }">
              <a-space>
                <a-button size="mini" type="outline" @click="runTask(record.name)" v-permission="'task.run'">
                  立即执行
                </a-button>
                <a-button size="mini" @click="openEdit(record)" v-permission="'task.edit'">
                  编辑
                </a-button>
                <a-popconfirm
                  content="确认删除该任务？"
                  type="warning"
                  @ok="deleteTask(record.id)"
                  v-permission="'task.delete'"
                >
                  <a-button size="mini" status="danger">删除</a-button>
                </a-popconfirm>
              </a-space>
            </template>
          </a-table-column>
        </template>
      </a-table>
    </a-card>

    <!-- 代码内置任务 -->
    <a-card :bordered="false" title="代码内置任务" style="margin-bottom: 16px">
      <a-table :data="staticTasks" :loading="loading" :pagination="false">
        <template #columns>
          <a-table-column title="任务名" data-index="name" :width="200" />
          <a-table-column title="Cron 规则" data-index="cron" :width="140" />
          <a-table-column title="最近一次" :width="280">
            <template #cell="{ record }">
              <a-space>
                <a-tag v-if="record.last_status === 1" color="green">成功</a-tag>
                <a-tag v-else-if="record.last_status === 0" color="red">失败</a-tag>
                <a-tag v-else-if="record.last_status === 2" color="blue">执行中</a-tag>
                <a-tag v-else>未执行</a-tag>
                <span>{{ record.last_run_at || '--' }}</span>
              </a-space>
            </template>
          </a-table-column>
          <a-table-column title="操作" :width="140">
            <template #cell="{ record }">
              <a-popconfirm
                :content="`确认立即执行「${record.name}」？`"
                type="warning"
                @ok="runTask(record.name)"
                v-permission="'task.run'"
              >
                <a-button size="small" type="outline">立即执行</a-button>
              </a-popconfirm>
            </template>
          </a-table-column>
        </template>
      </a-table>
    </a-card>

    <a-card :bordered="false" title="最近执行记录">
      <a-table :data="recent" :loading="loading" :pagination="false">
        <template #columns>
          <a-table-column title="任务名" data-index="name" :width="200" />
          <a-table-column title="触发" data-index="trigger" :width="100" align="center" />
          <a-table-column title="结果" :width="100" align="center">
            <template #cell="{ record }">
              <a-tag v-if="record.status === 1" color="green">成功</a-tag>
              <a-tag v-else-if="record.status === 0" color="red">失败</a-tag>
              <a-tag v-else color="blue">执行中</a-tag>
            </template>
          </a-table-column>
          <a-table-column title="耗时" :width="110" align="center">
            <template #cell="{ record }">{{ record.duration_ms }} ms</template>
          </a-table-column>
          <a-table-column title="错误" data-index="error" ellipsis />
          <a-table-column title="开始时间" data-index="created_at" :width="180" />
        </template>
      </a-table>
    </a-card>

    <!-- 动态任务编辑弹窗 -->
    <a-modal
      v-model:visible="editVisible"
      :title="editForm.id ? '编辑任务' : '新建任务'"
      :width="560"
      @ok="saveTask"
      @cancel="editVisible = false"
    >
      <a-form :model="editForm" auto-label-width>
        <a-form-item label="任务名" required>
          <a-input v-model="editForm.name" placeholder="唯一名称，如 每日对账" />
        </a-form-item>
        <a-form-item label="Cron 表达式" required help="常用频率直接选择；或切「自定义」可视化编辑">
          <cron-input v-model="editForm.cron" />
        </a-form-item>
        <a-form-item label="执行动作" required help="动作由代码登记（TaskRegistry::dynamic），后台只做编排">
          <a-select v-model="editForm.action">
            <a-option v-for="(item, key) in actionOptions" :key="key" :value="key">
              {{ item.title }}
            </a-option>
          </a-select>
        </a-form-item>
        <a-form-item label="备注">
          <a-textarea v-model="editForm.remark" :auto-size="{ minRows: 2, maxRows: 4 }" />
        </a-form-item>
        <a-form-item label="状态">
          <a-radio-group v-model="editForm.status">
            <a-radio :value="1">启用</a-radio>
            <a-radio :value="0">停用</a-radio>
          </a-radio-group>
        </a-form-item>
      </a-form>
    </a-modal>
  </a-card>
</template>

<script lang="ts" setup>
  import { computed, onMounted, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import BaseApi from '@/api/base';
  import CronInput from '@/components/cron-input/index.vue';
  import { getToken } from '@/utils/auth';

  const taskApi = new BaseApi('Task');

  const loading = ref(false);
  const staticTasks = ref<any[]>([]);
  const dynamic = ref<any[]>([]);
  const recent = ref<any[]>([]);

  const editVisible = ref(false);
  const saving = ref(false);
  const editForm = ref<any>({
    id: 0,
    name: '',
    cron: '*/5 * * * *',
    action: '',
    remark: '',
    status: 1,
  });

  // 动作选项来自 /auto/task/form 协议（TaskRegistry::dynamic 登记）
  const actionOptions = ref<Record<string, any>>({});

  async function load() {
    loading.value = true;
    try {
      const res: any = await axios.get('/task/list', {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
      });
      staticTasks.value = res.data?.static ?? [];
      dynamic.value = res.data?.dynamic ?? [];
      recent.value = res.data?.recent ?? [];
    } catch (e) {
      // 拦截器已提示
    } finally {
      loading.value = false;
    }
  }

  async function loadActions() {
    try {
      const res = await taskApi.getForm();
      for (const item of res.data ?? []) {
        if (item.field === 'action' && Array.isArray(item.data)) {
          const map: Record<string, any> = {};
          for (const opt of item.data) {
            map[opt.value] = { title: opt.label };
          }
          actionOptions.value = map;
        }
      }
    } catch (e) {
      // 忽略
    }
  }

  function openEdit(record?: any) {
    editForm.value = record
      ? { ...record }
      : { id: 0, name: '', cron: '*/5 * * * *', action: '', remark: '', status: 1 };
    editVisible.value = true;
  }

  async function saveTask() {
    if (!editForm.value.name || !editForm.value.cron || !editForm.value.action) {
      Message.error('请填写完整');
      return;
    }
    saving.value = true;
    const res = editForm.value.id
      ? await taskApi.updateItem(editForm.value.id, editForm.value)
      : await taskApi.createItem(editForm.value);
    saving.value = false;
    if (res.code === '0') {
      Message.success('已保存，任务进程将在 2 秒内热重载');
      editVisible.value = false;
      load();
    } else {
      Message.error(res.message);
    }
  }

  async function deleteTask(id: number) {
    const res = await taskApi.deleteItem(id);
    if (res.code === '0') {
      Message.success('已删除，任务进程热重载后失效');
      load();
    } else {
      Message.error(res.message);
    }
  }

  async function runTask(name: string) {
    try {
      const res: any = await axios.post(
        '/task/run',
        { name },
        {
          baseURL: import.meta.env.VITE_API_BASE_URL as string,
          headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        }
      );
      if (res.code === '0') {
        Message.success(res.message || '已触发');
        setTimeout(load, 1500);
      }
    } catch (e) {
      // 拦截器已提示
    }
  }

  onMounted(() => {
    load();
    loadActions();
  });
</script>

<style scoped></style>
