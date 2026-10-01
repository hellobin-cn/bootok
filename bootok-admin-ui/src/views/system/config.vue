<template>
  <crud
    model-name="system-config"
    model-text="系统配置"
    key-name="configId"
    :columns="columns"
  />
</template>

<script lang="ts" setup>
  import crud from '@/components/crud/index.vue';
  import type { TableColumnData } from '@arco-design/web-vue';
  import { ref } from 'vue';

  const columns = ref<TableColumnData[]>([
    { title: '配置键', dataIndex: 'name', width: 200 },
    { title: '配置名', dataIndex: 'title' },
    { title: '配置值', dataIndex: 'value', ellipsis: true, width: 300 },
    { title: '排序', dataIndex: 'sort', width: 90, align: 'center' },
  ]);
</script>

<style scoped></style>
