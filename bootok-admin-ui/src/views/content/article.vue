<template>
  <crud
    ref="crudRef"
    model-name="article"
    model-text="文章"
    key-name="articleId"
    :columns="columns"
    :filter="filter"
    modal-width="1000px"
  >
    <template #search>
      <a-card :bordered="false" style="margin-bottom: 12px">
        <a-space>
          <a-input-search
            v-model="query.title"
            placeholder="搜索标题"
            style="width: 240px"
            allow-clear
            @search="() => crudRef.getItems(1)"
            @clear="() => crudRef.getItems(1)"
          />
          <a-select
            v-model="query.status"
            placeholder="状态"
            style="width: 140px"
            allow-clear
            @change="() => crudRef.getItems(1)"
            @clear="() => crudRef.getItems(1)"
          >
            <a-option :value="1">上架</a-option>
            <a-option :value="0">下架</a-option>
          </a-select>
        </a-space>
      </a-card>
    </template>
    <template #cover="{ record }">
      <a-image
        v-if="record.cover"
        :src="record.cover"
        width="60"
        height="40"
        fit="cover"
      />
      <span v-else>--</span>
    </template>
    <template #status="{ record }">
      <a-tag v-if="record.status === 1" color="green">上架</a-tag>
      <a-tag v-else color="red">下架</a-tag>
    </template>
  </crud>
</template>

<script lang="ts" setup>
  import { computed, ref } from 'vue';
  import crud from '@/components/crud/index.vue';
  import type { TableColumnData } from '@arco-design/web-vue';

  const crudRef = ref();
  const query = ref({
    title: '',
    status: undefined as number | undefined,
  });

  const filter = computed(() => ({
    titleLike: query.value.title,
    status: query.value.status,
  }));

  const columns = ref<TableColumnData[]>([
    { title: '标题', dataIndex: 'title', ellipsis: true },
    { title: '封面', slotName: 'cover', width: 90, align: 'center' },
    { title: '状态', slotName: 'status', width: 90, align: 'center' },
    { title: '排序', dataIndex: 'sort', width: 90, align: 'center' },
  ]);
</script>

<style scoped></style>
