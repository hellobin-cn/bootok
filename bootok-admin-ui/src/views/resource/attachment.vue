<template>
  <crud
    model-name="attachment"
    model-text="附件"
    key-name="id"
    :columns="columns"
    :filter="filter"
    :add-enable="false"
    :edit-enable="false"
    :delete-enable="false"
    :action-enable="false"
    :show-update-time="false"
    :show-create-time="false"
    :batch="false"
  >
    <template #search>
      <a-card :bordered="false" style="margin-bottom: 12px">
        <a-space>
          <a-input-search
            v-model="query.adminName"
            placeholder="搜索上传人"
            style="width: 200px"
            allow-clear
            @search="() => crudRef.getItems(1)"
            @clear="() => crudRef.getItems(1)"
          />
          <a-select v-model="query.ext" placeholder="类型" style="width: 120px" allow-clear @change="() => crudRef.getItems(1)">
            <a-option v-for="e in EXT_OPTIONS" :key="e" :value="e">{{ e }}</a-option>
          </a-select>
        </a-space>
      </a-card>
    </template>
    <template #path="{ record }">
      <a-space>
        <a-image
          v-if="isImage(record.ext)"
          :src="record.url"
          :width="44"
          :height="44"
          fit="cover"
          style="border-radius: 4px; vertical-align: middle"
        />
        <a-link :href="record.url" target="_blank">
          {{ record.originalName || record.path }}
        </a-link>
      </a-space>
    </template>
    <template #ext="{ record }">
      <a-tag size="small">{{ record.ext?.toUpperCase() || '-' }}</a-tag>
    </template>
    <template #size="{ record }">
      {{ formatSize(record.size) }}
    </template>
  </crud>
</template>

<script lang="ts" setup>
  import { computed, ref } from 'vue';
  import crud from '@/components/crud/index.vue';
  import type { TableColumnData } from '@arco-design/web-vue';

  const crudRef = ref();
  const query = ref({ adminName: '', ext: undefined as string | undefined });

  const EXT_OPTIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'mp4', 'mp3', 'txt', 'csv', 'md'];
  const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

  function isImage(ext: string): boolean {
    return IMAGE_EXTS.includes((ext || '').toLowerCase());
  }

  const filter = computed(() => ({
    adminNameLike: query.value.adminName,
    ...(query.value.ext ? { ext: query.value.ext } : {}),
  }));

  const columns = ref<TableColumnData[]>([
    { title: '文件', slotName: 'path', ellipsis: true },
    { title: '类型', slotName: 'ext', width: 90, align: 'center' },
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
