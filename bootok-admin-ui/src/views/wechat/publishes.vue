<template>
  <a-card :bordered="false" title="公众号发布记录">
    <template #extra>
      <a-button size="small" :loading="loading" @click="load">刷新</a-button>
    </template>
    <a-table
      :bordered="false"
      :data="items"
      :loading="loading"
      :pagination="pagination"
      @page-change="changePage"
    >
      <template #columns>
        <a-table-column title="文章" data-index="title" ellipsis />
        <a-table-column title="状态" :width="110" align="center">
          <template #cell="{ record }">
            <a-tag size="small" :color="statusColor(record.status)">{{ statusText(record.status) }}</a-tag>
          </template>
        </a-table-column>
        <a-table-column title="草稿 media_id" :width="220">
          <template #cell="{ record }">
            <span class="mono">{{ record.draft_media_id || '--' }}</span>
          </template>
        </a-table-column>
        <a-table-column title="错误" data-index="error" ellipsis />
        <a-table-column title="时间" :width="175">
          <template #cell="{ record }">{{ record.created_at }}</template>
        </a-table-column>
        <a-table-column title="操作" :width="150" align="center">
          <template #cell="{ record }">
            <a-space>
              <a-button
                v-if="record.publish_id && record.status === 2"
                type="text"
                size="small"
                @click="refresh(record)"
              >
                查发布状态
              </a-button>
              <a-popconfirm content="确认删除该记录？" type="warning" @ok="remove(record)">
                <a-button type="text" size="small" status="danger">删除</a-button>
              </a-popconfirm>
            </a-space>
          </template>
        </a-table-column>
      </template>
    </a-table>
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

  const pagination = computed(() => ({
    total: total.value,
    current: page.value,
    pageSize,
    showTotal: true,
  }));

  const statusText = (s: number) => ({ 0: '失败', 1: '草稿', 2: '已提交发布', 3: '发布成功' })[s] ?? '未知';
  const statusColor = (s: number) => ({ 0: 'red', 1: 'gray', 2: 'orange', 3: 'green' })[s] ?? 'gray';

  async function load() {
    loading.value = true;
    try {
      const res: any = await axios.get('/wechat/publish/rows', {
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

  async function refresh(record: any) {
    const res: any = await axios.post(`/wechat/publish/${record.id}/refresh`, {});
    Message.success(res.message || '已刷新');
    await load();
  }

  async function remove(record: any) {
    await axios.delete(`/wechat/publish/${record.id}`);
    Message.success('删除成功');
    await load();
  }

  onMounted(load);
</script>

<style scoped lang="less">
  .mono {
    font-family: Consolas, monospace;
    font-size: 12px;
  }
</style>
