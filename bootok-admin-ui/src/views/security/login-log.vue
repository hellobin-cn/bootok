<template>
  <crud
    ref="crudRef"
    model-name="login-log"
    model-text="登录日志"
    key-name="id"
    :columns="columns"
    :add-enable="false"
    :edit-enable="false"
    :delete-enable="false"
    :action-enable="false"
    :show-update-time="false"
    :batch="false"
    :filter="filter"
  >
    <template #search>
      <a-card :bordered="false" style="margin-bottom: 12px">
        <a-space>
          <a-input-search
            v-model="query.adminName"
            placeholder="搜索用户名"
            style="width: 200px"
            allow-clear
            @search="() => crudRef.getItems(1)"
            @clear="() => crudRef.getItems(1)"
          />
          <a-select
            v-model="query.status"
            placeholder="结果"
            style="width: 120px"
            allow-clear
            @change="() => crudRef.getItems(1)"
            @clear="() => crudRef.getItems(1)"
          >
            <a-option :value="1">成功</a-option>
            <a-option :value="0">失败</a-option>
          </a-select>
        </a-space>
      </a-card>
    </template>
    <template #status="{ record }">
      <a-tag v-if="record.status === 1" color="green">成功</a-tag>
      <a-tag v-else color="red">失败</a-tag>
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
    status: undefined as number | undefined,
  });

  const filter = computed(() => ({
    adminNameLike: query.value.adminName,
    status: query.value.status,
  }));

  const columns = ref<TableColumnData[]>([
    { title: '用户名', dataIndex: 'adminName', width: 140 },
    { title: '结果', slotName: 'status', width: 90, align: 'center' },
    { title: '失败原因', dataIndex: 'message', width: 160, ellipsis: true },
    { title: 'IP', dataIndex: 'ip', width: 140 },
    { title: 'UA', dataIndex: 'userAgent', ellipsis: true },
  ]);
</script>

<style scoped></style>
