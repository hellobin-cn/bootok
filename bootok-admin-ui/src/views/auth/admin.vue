<template>
  <crud
    ref="crudRef"
    model-name="admin"
    model-text="管理员"
    key-name="adminId"
    :columns="columns"
    :show-id="false"
    :filter="filter"
  >
    <template #search>
      <a-card :bordered="false" style="margin-bottom: 12px">
        <a-input-search
          v-model="query.adminName"
          placeholder="搜索用户名"
          style="width: 260px"
          allow-clear
          @search="() => crudRef.getItems(1)"
          @clear="() => crudRef.getItems(1)"
        />
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
  });

  // BaseApi 会把驼峰键转为下划线：adminNameLike -> admin_name_like
  const filter = computed(() => ({
    adminNameLike: query.value.adminName,
  }));

  const columns = ref<TableColumnData[]>([
    { title: '用户名', dataIndex: 'adminName' },
    { title: '超级管理员', dataIndex: 'isSuper', width: 110, align: 'center' },
  ]);
</script>

<style scoped></style>
