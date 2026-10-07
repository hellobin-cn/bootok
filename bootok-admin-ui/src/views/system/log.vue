<template>
  <a-card :bordered="false" title="运行日志">
    <a-space style="margin-bottom: 12px" wrap>
      <a-select
        v-model="file"
        :options="fileOptions"
        :style="{ width: '220px' }"
        placeholder="选择日志文件"
        @change="reload"
      />
      <a-select v-model="lines" :style="{ width: '120px' }" @change="load">
        <a-option :value="100">最近 100 行</a-option>
        <a-option :value="200">最近 200 行</a-option>
        <a-option :value="500">最近 500 行</a-option>
      </a-select>
      <a-input
        v-model="keyword"
        placeholder="关键字过滤（含上下文）"
        :style="{ width: '260px' }"
        allow-clear
        @press-enter="load"
      />
      <a-button type="primary" :loading="loading" @click="load">搜索</a-button>
      <a-checkbox v-model="autoRefresh" @change="setupTimer">自动刷新（15s）</a-checkbox>
    </a-space>
    <a-table
      :bordered="false"
      :data="rows"
      :loading="loading"
      :pagination="pagination"
      :scroll="{ y: 520 }"
      size="small"
      @page-change="changePage"
    >
      <template #columns>
        <a-table-column title="时间" :width="175">
          <template #cell="{ record }">{{ record.time || '--' }}</template>
        </a-table-column>
        <a-table-column title="级别" :width="95" align="center">
          <template #cell="{ record }">
            <a-tag size="small" :color="levelColor(record.level)">{{ record.level }}</a-tag>
          </template>
        </a-table-column>
        <a-table-column title="通道" :width="100" align="center">
          <template #cell="{ record }">{{ record.channel || '--' }}</template>
        </a-table-column>
        <a-table-column title="消息" data-index="message" ellipsis :tooltip="true" />
      </template>
    </a-table>
  </a-card>
</template>

<script lang="ts" setup>
  import { computed, onMounted, onUnmounted, ref } from 'vue';
  import axios from 'axios';

  const files = ref<any[]>([]);
  const file = ref('');
  const lines = ref(200);
  const keyword = ref('');
  const rows = ref<any[]>([]);
  const loading = ref(false);
  const autoRefresh = ref(true);
  const page = ref(1);
  const pageSize = 50;
  let timer = 0;

  const fileOptions = computed(() =>
    files.value.map((f) => ({ label: `${f.name}（${Math.max(1, Math.round(f.size / 1024))} KB）`, value: f.name }))
  );
  const pagination = computed(() => ({
    total: rows.value.length,
    current: page.value,
    pageSize,
    showTotal: true,
  }));

  const levelColor = (level: string) => {
    const lv = level.toUpperCase();
    if (lv.includes('CRITICAL') || lv.includes('ALERT') || lv.includes('EMERGENCY')) return 'purple';
    if (lv.includes('ERROR')) return 'red';
    if (lv.includes('WARNING') || lv.includes('NOTICE')) return 'orangered';
    if (lv.includes('DEBUG')) return 'gray';
    return 'arcoblue';
  };

  async function loadFiles() {
    const res: any = await axios.get('/log/files');
    files.value = res.data ?? [];
    if (!file.value && files.value.length) {
      file.value = files.value[0].name;
    }
  }

  async function load() {
    if (!file.value) return;
    loading.value = true;
    try {
      const res: any = await axios.get('/log/tail', {
        params: { file: file.value, lines: lines.value, keyword: keyword.value || undefined },
      });
      rows.value = res.data ?? [];
      page.value = 1;
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
  }

  function setupTimer() {
    window.clearInterval(timer);
    if (autoRefresh.value) {
      timer = window.setInterval(load, 15000);
    }
  }

  onMounted(async () => {
    await loadFiles();
    await load();
    setupTimer();
  });
  onUnmounted(() => window.clearInterval(timer));
</script>
