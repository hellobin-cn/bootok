<template>
  <crud
    model-name="group"
    model-text="权限组"
    key-name="groupId"
    :columns="columns"
    @edit="gotoEdit"
  />
</template>

<script lang="ts" setup>
  import crud from '@/components/crud/index.vue';
  import { ref } from 'vue';
  import type { TableColumnData } from '@arco-design/web-vue';
  import { useRouter } from 'vue-router';

  const router = useRouter();

  const columns = ref<TableColumnData[]>([
    { title: '名称', dataIndex: 'groupName', ellipsis: true },
  ]);

  // 拦截新建/编辑，统一进入权限编辑页（勾选路由与权限点）
  function gotoEdit(func: Function) {
    const itemId = func(true);
    router.push(`/auth/group-edit?groupId=${itemId || 0}`);
  }
</script>

<style scoped></style>
