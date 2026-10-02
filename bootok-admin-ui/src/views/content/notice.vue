<template>
  <crud
    model-name="notice"
    model-text="公告"
    key-name="noticeId"
    :columns="columns"
  >
    <template #status="{ record }">
      <a-tag v-if="record.status === 1" color="green">发布</a-tag>
      <a-tag v-else color="gray">下线</a-tag>
    </template>
  </crud>
</template>

<script lang="ts" setup>
  import crud from '@/components/crud/index.vue';
  import { ref } from 'vue';
  import type { TableColumnData } from '@arco-design/web-vue';

  const columns = ref<TableColumnData[]>([
    { title: '标题', dataIndex: 'title', ellipsis: true },
    { title: '状态', slotName: 'status', width: 90, align: 'center' },
    { title: '排序', dataIndex: 'sort', width: 90, align: 'center' },
  ]);
</script>

<style scoped></style>
