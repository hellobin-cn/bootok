<template>
  <crud
    ref="crudRef"
    model-name="operation-log"
    model-text="操作日志"
    key-name="id"
    :columns="columns"
    :add-enable="false"
    :edit-enable="false"
    :delete-enable="false"
    :action-enable="false"
    :show-update-time="false"
    :filter="filter"
  >
    <template #search>
      <a-card :bordered="false" style="margin-bottom: 12px">
        <a-space>
          <a-input-search
            v-model="query.adminName"
            placeholder="搜索操作人"
            style="width: 200px"
            allow-clear
            @search="() => crudRef.getItems(1)"
            @clear="() => crudRef.getItems(1)"
          />
          <a-input-search
            v-model="query.path"
            placeholder="搜索请求路径"
            style="width: 260px"
            allow-clear
            @search="() => crudRef.getItems(1)"
            @clear="() => crudRef.getItems(1)"
          />
        </a-space>
      </a-card>
    </template>
  </crud>
</template>

<script lang="ts" setup>
  import { computed, ref } from 'vue';
  import crud from '@/components/crud/index.vue';
  import type { TableColumnData } from '@arco-design/web-vue';

  const crudRef = ref();
  const query = ref({
    adminName: '',
    path: '',
  });

  const filter = computed(() => ({
    adminNameLike: query.value.adminName,
    pathLike: query.value.path,
  }));

  const columns = ref<TableColumnData[]>([
    { title: '操作人', dataIndex: 'adminName', width: 120 },
    { title: '方法', dataIndex: 'method', width: 90, align: 'center' },
    { title: '路径', dataIndex: 'path', ellipsis: true },
    { title: 'IP', dataIndex: 'ip', width: 130 },
    { title: '参数', dataIndex: 'params', ellipsis: true, width: 320 },
  ]);
</script>

<style scoped></style>
