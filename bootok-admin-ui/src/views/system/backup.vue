<template>
  <a-card :bordered="false" title="数据库备份">
    <template #extra>
      <a-space>
        <a-button type="primary" :loading="running" @click="runBackup">
          立即备份
        </a-button>
      </a-space>
    </template>
    <a-alert style="margin-bottom: 12px">
      备份为全量 SQL（gzip 压缩），存到默认存储渠道；每日 02:30 自动备份，保留
      {{ backupKeep }} 份。恢复会覆盖当前数据库，属高危操作。
    </a-alert>
    <a-table
      :bordered="false"
      :data="items"
      :loading="loading"
      :pagination="pagination"
      @page-change="changePage"
    >
      <template #columns>
        <a-table-column title="文件名" data-index="filename" />
        <a-table-column title="大小" :width="110">
          <template #cell="{ record }">{{ formatSize(record.size) }}</template>
        </a-table-column>
        <a-table-column title="表数量" :width="90">
          <template #cell="{ record }">{{ record.tables || '--' }}</template>
        </a-table-column>
        <a-table-column title="触发方式" :width="100">
          <template #cell="{ record }">
            <a-tag size="small" :color="record.trigger === 'task' ? 'arcoblue' : 'green'">
              {{ record.trigger === 'task' ? '定时' : '手动' }}
            </a-tag>
          </template>
        </a-table-column>
        <a-table-column title="状态" :width="90">
          <template #cell="{ record }">
            <a-tag size="small" :color="record.status === 1 ? 'green' : 'red'">
              {{ record.status === 1 ? '成功' : '失败' }}
            </a-tag>
          </template>
        </a-table-column>
        <a-table-column title="耗时" :width="100">
          <template #cell="{ record }">
            {{ record.duration_ms ? `${(record.duration_ms / 1000).toFixed(1)}s` : '--' }}
          </template>
        </a-table-column>
        <a-table-column title="时间" :width="170">
          <template #cell="{ record }">{{ record.created_at }}</template>
        </a-table-column>
        <a-table-column title="操作" :width="200">
          <template #cell="{ record }">
            <a-space>
              <a-button
                v-if="record.status === 1"
                type="text"
                size="small"
                @click="download(record)"
              >
                下载
              </a-button>
              <a-button
                v-if="record.status === 1"
                type="text"
                size="small"
                status="warning"
                @click="askRestore(record)"
              >
                恢复
              </a-button>
              <a-popconfirm content="确认删除该备份（含存储渠道文件）？" type="warning" @ok="removeBackup(record)">
                <a-button type="text" size="small" status="danger">删除</a-button>
              </a-popconfirm>
            </a-space>
          </template>
        </a-table-column>
      </template>
    </a-table>

    <!-- 恢复二次确认：输入「恢复」方可执行 -->
    <a-modal
      v-model:visible="restoreVisible"
      title="恢复数据库"
      :width="460"
      :ok-button-props="{ disabled: restoreText !== '恢复' }"
      :ok-loading="restoring"
      ok-status="danger"
      ok-text="确认恢复"
      :unmount-on-close="true"
      @ok="confirmRestore"
      @cancel="restoreVisible = false"
    >
      <a-alert type="warning" style="margin-bottom: 12px">
        恢复会以「{{ restoreTarget?.filename }}」覆盖当前数据库的全部数据，恢复期间写入的数据将丢失。请输入「恢复」确认。
      </a-alert>
      <a-input v-model="restoreText" placeholder="恢复" allow-clear />
    </a-modal>
  </a-card>
</template>

<script lang="ts" setup>
  import { computed, onMounted, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';

  const items = ref<any[]>([]);
  const total = ref(0);
  const loading = ref(false);
  const page = ref(1);
  const pageSize = 15;
  const running = ref(false);
  const restoreVisible = ref(false);
  const restoreText = ref('');
  const restoring = ref(false);
  const restoreTarget = ref<any>(null);
  const backupKeep = ref(7);

  const pagination = computed(() => ({
    total: total.value,
    current: page.value,
    pageSize,
    showTotal: true,
  }));

  const formatSize = (bytes: number) => {
    if (!bytes) return '--';
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
  };

  async function load() {
    loading.value = true;
    try {
      const res: any = await axios.get('/backup/rows', {
        params: { page: page.value, page_size: pageSize },
      });
      items.value = res.data?.data ?? [];
      total.value = res.data?.total ?? 0;
    } finally {
      loading.value = false;
    }
  }

  function changePage(p: number) {
    page.value = p;
    load();
  }

  async function runBackup() {
    running.value = true;
    try {
      await axios.post('/backup/run', {}, { timeout: 300000 });
      Message.success('备份完成');
      await load();
    } catch {
      // 错误信封由拦截器统一提示
    } finally {
      running.value = false;
    }
  }

  async function download(record: any) {
    const res = await axios.get(`/backup/${record.backup_id}/download`, {
      responseType: 'blob',
      timeout: 300000,
    });
    const contentType = String(res.headers['content-type'] ?? '');
    if (contentType.includes('application/json')) {
      const text = await (res.data as Blob).text();
      Message.error(JSON.parse(text).message || '下载失败');
      return;
    }
    const url = URL.createObjectURL(res.data as Blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = record.filename;
    a.click();
    URL.revokeObjectURL(url);
  }

  function askRestore(record: any) {
    restoreTarget.value = record;
    restoreText.value = '';
    restoreVisible.value = true;
  }

  async function confirmRestore() {
    if (!restoreTarget.value) return;
    restoring.value = true;
    try {
      await axios.post(`/backup/${restoreTarget.value.backup_id}/restore`, {}, { timeout: 600000 });
      Message.success('恢复完成，页面将刷新以加载恢复后的数据');
      restoreVisible.value = false;
      window.setTimeout(() => window.location.reload(), 1200);
    } catch {
      // 错误信封由拦截器统一提示
    } finally {
      restoring.value = false;
    }
  }

  async function removeBackup(record: any) {
    await axios.delete(`/backup/${record.backup_id}`);
    Message.success('删除成功');
    await load();
  }

  onMounted(async () => {
    await load();
    try {
      const res: any = await axios.get('/config/settings');
      const row = (res.data ?? []).find((c: any) => c.name === 'backup_keep');
      if (row) backupKeep.value = Number(row.value) || 7;
    } catch {
      // 保持默认
    }
  });
</script>
