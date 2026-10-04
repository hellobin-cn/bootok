<template>
  <a-card :bordered="false" title="发送记录">
    <a-table
      :data="rows"
      :loading="loading"
      :pagination="pagination"
      @page-change="onPageChange"
    >
      <template #columns>
        <a-table-column title="ID" data-index="id" :width="70" />
        <a-table-column title="收件人" data-index="toAddress" :width="200" ellipsis tooltip />
        <a-table-column title="主题" data-index="subject" ellipsis tooltip />
        <a-table-column title="模板" :width="130">
          <template #cell="{ record }">
            <span v-if="record.templateCode">{{ record.templateCode }}</span>
            <span v-else style="color: var(--color-text-3)">自由内容</span>
          </template>
        </a-table-column>
        <a-table-column title="状态" :width="90">
          <template #cell="{ record }">
            <a-tag v-if="record.status === 1" color="green" size="small">成功</a-tag>
            <a-tooltip v-else :content="record.error || '发送失败'">
              <a-tag color="red" size="small">失败</a-tag>
            </a-tooltip>
          </template>
        </a-table-column>
        <a-table-column title="耗时" :width="90">
          <template #cell="{ record }">{{ record.costMs }}ms</template>
        </a-table-column>
        <a-table-column title="时间" data-index="createdAt" :width="170" />
        <a-table-column title="操作" :width="160">
          <template #cell="{ record }">
            <a-space>
              <a-button size="mini" @click="openDetail(record)">详情</a-button>
              <a-popconfirm content="按快照原样重发该邮件？" type="warning" @ok="resend(record.id)">
                <a-button size="mini" type="outline" :loading="resendingId === record.id">重发</a-button>
              </a-popconfirm>
            </a-space>
          </template>
        </a-table-column>
      </template>
    </a-table>

    <!-- 详情抽屉：渲染后快照 -->
    <a-drawer v-model:visible="detailVisible" title="邮件详情" :width="640" :footer="false">
      <a-descriptions :column="1" size="medium" class="detail-meta">
        <a-descriptions-item label="收件人">{{ detail?.toAddress }}</a-descriptions-item>
        <a-descriptions-item label="主题">{{ detail?.subject }}</a-descriptions-item>
        <a-descriptions-item label="模板">{{ detail?.templateCode || '自由内容' }}</a-descriptions-item>
        <a-descriptions-item label="状态">
          <a-tag v-if="detail?.status === 1" color="green" size="small">成功</a-tag>
          <a-tag v-else color="red" size="small">失败：{{ detail?.error }}</a-tag>
        </a-descriptions-item>
        <a-descriptions-item label="时间">{{ detail?.createdAt }}</a-descriptions-item>
      </a-descriptions>
      <iframe :srcdoc="detail?.body ?? ''" class="detail-frame" sandbox="" />
    </a-drawer>
  </a-card>
</template>

<script lang="ts" setup>
  import { onMounted, reactive, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import BaseApi from '@/api/base';
  import { getToken } from '@/utils/auth';

  const logApi = new BaseApi('MailLog');

  const rows = ref<any[]>([]);
  const loading = ref(false);
  const page = ref(1);
  const pageSize = 20;
  const pagination = reactive({
    total: 0,
    current: 1,
    pageSize,
    showTotal: true,
  });

  async function loadRows() {
    loading.value = true;
    try {
      const res = await logApi.getRows(page.value, {}, pageSize);
      rows.value = res.data?.data ?? [];
      pagination.total = res.data?.total ?? 0;
      pagination.current = page.value;
    } finally {
      loading.value = false;
    }
  }

  function onPageChange(next: number) {
    page.value = next;
    loadRows();
  }

  // 详情抽屉
  const detailVisible = ref(false);
  const detail = ref<any>(null);

  async function openDetail(record: any) {
    const res = await logApi.getItem(record.id);
    if (res.code === '0') {
      detail.value = res.data;
      detailVisible.value = true;
    }
  }

  // 重发（按快照原样重投，不重渲染）
  const resendingId = ref<number | null>(null);

  async function resend(id: number) {
    resendingId.value = id;
    try {
      const res: any = await axios.post(`/mail/log/${id}/resend`, {}, {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        timeout: 30000,
      });
      if (res.code === '0') {
        Message.success(res.data?.message || '已重发');
        loadRows();
      }
    } catch (e: any) {
      Message.error(e?.message ?? '重发失败');
    } finally {
      resendingId.value = null;
    }
  }

  onMounted(loadRows);
</script>

<style scoped>
  .detail-meta {
    margin-bottom: 12px;
  }
  .detail-frame {
    width: 100%;
    height: 560px;
    border: 1px solid var(--color-border-2);
    border-radius: 4px;
    background: #fff;
  }
</style>
