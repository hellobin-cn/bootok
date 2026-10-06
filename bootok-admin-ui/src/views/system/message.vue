<template>
  <a-card :bordered="false" title="消息中心">
    <template #extra>
      <a-space>
        <a-radio-group
          v-model="onlyUnread"
          type="button"
          size="small"
          @change="reload"
        >
          <a-radio :value="false">全部</a-radio>
          <a-radio :value="true">未读</a-radio>
        </a-radio-group>
        <a-button size="small" :disabled="!unread" @click="readAll">
          全部已读
        </a-button>
      </a-space>
    </template>
    <a-table
      :bordered="false"
      :data="items"
      :loading="loading"
      :pagination="pagination"
      @page-change="changePage"
    >
      <template #columns>
        <a-table-column title="类型" :width="90">
          <template #cell="{ record }">
            <a-tag size="small" :color="colorOf(record.type)">
              {{ textOf(record.type) }}
            </a-tag>
          </template>
        </a-table-column>
        <a-table-column title="标题">
          <template #cell="{ record }">
            <a-link @click="open(record)">
              <span :class="{ 'msg-unread': !record.is_read }">
                {{ record.title }}
              </span>
            </a-link>
            <div v-if="record.content" class="msg-content">
              {{ record.content }}
            </div>
          </template>
        </a-table-column>
        <a-table-column title="时间" :width="180">
          <template #cell="{ record }">{{ record.created_at }}</template>
        </a-table-column>
        <a-table-column title="操作" :width="150">
          <template #cell="{ record }">
            <a-space>
              <a-button
                v-if="!record.is_read"
                type="text"
                size="small"
                @click="markRead(record)"
              >
                标已读
              </a-button>
              <a-popconfirm
                content="确认删除该消息？"
                type="warning"
                @ok="removeMsg(record)"
              >
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
  import { useRouter } from 'vue-router';
  import axios from 'axios';

  const router = useRouter();
  const items = ref<any[]>([]);
  const total = ref(0);
  const unread = ref(0);
  const loading = ref(false);
  const page = ref(1);
  const pageSize = 15;
  const onlyUnread = ref(false);

  const pagination = computed(() => ({
    total: total.value,
    current: page.value,
    pageSize,
    showTotal: true,
  }));

  const typeMap: Record<string, { text: string; color: string }> = {
    notice: { text: '通知', color: 'gray' },
    task: { text: '任务', color: 'orangered' },
    ssl: { text: '证书', color: 'arcoblue' },
    security: { text: '安全', color: 'red' },
  };
  const textOf = (type: string) => typeMap[type]?.text ?? '通知';
  const colorOf = (type: string) => typeMap[type]?.color ?? 'gray';

  async function load() {
    loading.value = true;
    try {
      const res: any = await axios.get('/message/rows', {
        params: { page: page.value, page_size: pageSize, ...(onlyUnread.value ? { is_read: 0 } : {}) },
      });
      items.value = res.data?.items ?? [];
      total.value = res.data?.total ?? 0;
      unread.value = res.data?.unread ?? 0;
    } finally {
      loading.value = false;
    }
  }

  function reload() {
    page.value = 1;
    load();
  }

  function changePage(p: number) {
    page.value = p;
    load();
  }

  async function markRead(record: any) {
    await axios.post('/message/read', { ids: [record.message_id] });
    await load();
  }

  async function readAll() {
    await axios.post('/message/read-all', {});
    await load();
  }

  async function removeMsg(record: any) {
    await axios.delete(`/message/${record.message_id}`);
    await load();
  }

  // 点击消息：标已读 + 有链接则跳转
  async function open(record: any) {
    if (!record.is_read) {
      await markRead(record);
    }
    if (record.link) {
      router.push(record.link);
    }
  }

  onMounted(load);
</script>

<style scoped lang="less">
  .msg-unread {
    font-weight: 600;
  }
  .msg-content {
    font-size: 12px;
    color: var(--color-text-3);
    margin-top: 2px;
    word-break: break-all;
  }
</style>
