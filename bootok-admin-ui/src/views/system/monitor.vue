<template>
  <a-card :bordered="false" title="系统监控">
    <template #extra>
      <a-button size="small" :loading="loading" @click="load">刷新</a-button>
    </template>
    <a-spin :loading="loading" style="width: 100%">
      <a-row :gutter="16">
        <a-col :span="12">
          <a-card :bordered="false" class="inner-card" title="服务器">
            <a-descriptions :column="1" size="medium" :data="serverDesc" />
            <template v-if="s.server.mem_total">
              <div class="label">内存</div>
              <a-progress :percent="memPercent" :status="memPercent > 0.9 ? 'danger' : 'normal'">
                <template #text>
                  {{ formatSize(s.server.mem_total - s.server.mem_free) }} /
                  {{ formatSize(s.server.mem_total) }}
                </template>
              </a-progress>
            </template>
            <div class="label">磁盘</div>
            <a-progress
              v-if="s.server.disk_total"
              :percent="diskPercent"
              :status="diskPercent > 0.9 ? 'danger' : 'normal'"
            >
              <template #text>
                {{ formatSize(s.server.disk_total - s.server.disk_free) }} /
                {{ formatSize(s.server.disk_total) }}
              </template>
            </a-progress>
          </a-card>
        </a-col>
        <a-col :span="12">
          <a-card :bordered="false" class="inner-card" title="PHP / 进程">
            <a-descriptions :column="1" size="medium" :data="phpDesc" />
          </a-card>
        </a-col>
        <a-col :span="12">
          <a-card :bordered="false" class="inner-card" title="MySQL">
            <a-descriptions :column="1" size="medium" :data="mysqlDesc" />
          </a-card>
        </a-col>
        <a-col :span="12">
          <a-card :bordered="false" class="inner-card" title="Redis / 队列 / 任务">
            <a-descriptions :column="1" size="medium" :data="redisDesc" />
            <div class="label">队列（pending / delayed / failed）</div>
            <a-progress v-if="q" :percent="queuePercent" :status="q.failed > 0 ? 'warning' : 'normal'">
              <template #text>{{ q.pending }} / {{ q.delayed }} / {{ q.failed }}</template>
            </a-progress>
          </a-card>
        </a-col>
      </a-row>
    </a-spin>
  </a-card>
</template>

<script lang="ts" setup>
  import { computed, onMounted, onUnmounted, ref } from 'vue';
  import axios from 'axios';

  const loading = ref(false);
  const s = ref<any>({ server: {}, php: {}, mysql: null, redis: null, queue: null });
  const q = computed(() => s.value.queue);
  let timer = 0;

  const formatSize = (bytes: number | null | undefined) => {
    if (!bytes) return '--';
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    if (bytes < 1024 * 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
    return `${(bytes / 1024 / 1024 / 1024).toFixed(2)} GB`;
  };
  const ago = (ts: number) =>
    ts ? `${Math.max(0, Math.round(Date.now() / 1000 - ts))} 秒前` : '--';

  // Arco Progress 的 percent 取 0~1
  const memPercent = computed(() =>
    s.value.server.mem_total
      ? (s.value.server.mem_total - s.value.server.mem_free) / s.value.server.mem_total
      : 0
  );
  const diskPercent = computed(() =>
    s.value.server.disk_total
      ? (s.value.server.disk_total - s.value.server.disk_free) / s.value.server.disk_total
      : 0
  );
  const queuePercent = computed(() => {
    if (!q.value) return 0;
    const sum = q.value.pending + q.value.delayed + q.value.failed;
    return Math.min(100, sum);
  });

  const serverDesc = computed(() => [
    { label: '操作系统', value: s.value.server.os ?? '--' },
    { label: '负载均值', value: (s.value.server.load ?? []).join(' / ') || '--' },
  ]);
  const phpDesc = computed(() => [
    { label: '版本 / SAPI', value: `${s.value.php.version ?? '--'}（${s.value.php.sapi ?? '--'}）` },
    { label: '进程内存', value: `${formatSize(s.value.php.process_mem)}（峰值 ${formatSize(s.value.php.peak_mem)}）` },
    { label: 'memory_limit', value: s.value.php.memory_limit ?? '--' },
    { label: 'OPcache', value: s.value.php.opcache ? '已启用' : '未启用' },
  ]);
  const mysqlDesc = computed(() => [
    { label: '版本', value: s.value.mysql?.version ?? '不可用' },
    { label: '数据量', value: `${formatSize(s.value.mysql?.data_size)}（${s.value.mysql?.tables ?? '--'} 张表）` },
    { label: '连接数', value: s.value.mysql?.connections ?? '--' },
  ]);
  const redisDesc = computed(() => [
    { label: '内存 / 键数', value: s.value.redis ? `${formatSize(s.value.redis.used_memory)} / ${s.value.redis.keys}` : '不可用' },
    { label: '队列心跳', value: s.value.queue ? ago(s.value.queue.heartbeat) : '--' },
    { label: '任务进程心跳', value: ago(s.value.crontab_heartbeat) },
  ]);

  async function load() {
    loading.value = true;
    try {
      const res: any = await axios.get('/monitor/system');
      s.value = res.data ?? {};
    } finally {
      loading.value = false;
    }
  }

  onMounted(() => {
    load();
    timer = window.setInterval(load, 15000);
  });
  onUnmounted(() => window.clearInterval(timer));
</script>

<style scoped lang="less">
  .inner-card {
    margin-bottom: 16px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
  }
  .label {
    margin: 8px 0 4px;
    font-size: 13px;
    color: var(--color-text-3);
  }
</style>
