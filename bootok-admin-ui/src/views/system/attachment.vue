<template>
  <crud
    model-name="attachment"
    model-text="附件"
    key-name="id"
    :columns="columns"
    :add-enable="false"
    :edit-enable="false"
    :delete-enable="false"
    :action-enable="false"
    :show-update-time="false"
    :batch="false"
  >
    <template #search>
      <a-card :bordered="false" style="margin-bottom: 12px">
        <a-input-search
          v-model="query.adminName"
          placeholder="搜索上传人"
          style="width: 200px"
          allow-clear
          @search="() => crudRef.getItems(1)"
          @clear="() => crudRef.getItems(1)"
        />
      </a-card>
    </template>
    <template #size="{ record }">
      {{ formatSize(record.size) }}
    </template>
    <template #path="{ record }">
      <a-link v-if="record.path" :href="record.path" target="_blank">
        {{ record.originalName || record.path }}
      </a-link>
    </template>
  </crud>
</template>

<script lang="ts" setup>
  import { computed, ref } from 'vue';
  import crud from '@/components/crud/index.vue';
  import type { TableColumnData } from '@arco-design/web-vue';

  const crudRef = ref();
  const query = ref({ adminName: '' });

  const filter = computed(() => ({
    adminNameLike: query.value.adminName,
  }));

  const columns = ref<TableColumnData[]>([
    { title: '文件', slotName: 'path', ellipsis: true },
    { title: '大小', slotName: 'size', width: 100, align: 'center' },
    { title: '上传人', dataIndex: 'adminName', width: 120 },
    { title: '创建时间', dataIndex: 'createdAt', width: 180, align: 'center' },
  ]);

  function formatSize(bytes: number): string {
    if (!bytes) return '--';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
  }
</script>

<style scoped></style>
