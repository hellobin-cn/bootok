<template>
  <div>
    <a-card :bordered="false" title="SSL 证书列表">
      <template #extra>
        <a-space>
          <a-button type="primary" @click="openIssue" v-if="userStore.isSuper">
            <template #icon><icon-plus /></template>
            签发证书
          </a-button>
        </a-space>
      </template>

      <a-table :data="rows" :loading="loading" :pagination="false">
        <template #columns>
          <a-table-column title="ID" data-index="id" :width="70" />
          <a-table-column title="域名" :width="260">
            <template #cell="{ record }">
              <a-tag v-for="d in parseDomains(record)" :key="d" style="margin-bottom: 4px">{{ d }}</a-tag>
            </template>
          </a-table-column>
          <a-table-column title="CA" data-index="ca" :width="110" align="center">
            <template #cell="{ record }">
              <a-tag v-if="record.ca === 'production'" color="orangered">生产</a-tag>
              <a-tag v-else color="gray">staging</a-tag>
            </template>
          </a-table-column>
          <a-table-column title="状态" :width="100" align="center">
            <template #cell="{ record }">
              <a-tag v-if="record.status === 'issued'" color="green">已签发</a-tag>
              <a-tag v-else-if="record.status === 'failed'" color="red">失败</a-tag>
              <a-tag v-else color="blue">{{ record.status }}</a-tag>
            </template>
          </a-table-column>
          <a-table-column title="到期时间" :width="170">
            <template #cell="{ record }">{{ record.expiresAt ?? '-' }}</template>
          </a-table-column>
          <a-table-column title="操作" :width="290">
            <template #cell="{ record }">
              <a-space>
                <a-button size="mini" type="outline" @click="openProgress(record.id)">进度</a-button>
                <a-button
                  size="mini" type="outline" v-if="record.status === 'issued'"
                  @click="download(record.id, 'fullchain')"
                >证书</a-button>
                <a-button
                  size="mini" type="outline" v-if="record.status === 'issued'"
                  @click="download(record.id, 'privkey')"
                >私钥</a-button>
                <a-popconfirm content="确认删除该任务？" type="warning" @ok="deleteTask(record.id)">
                  <a-button size="mini" status="danger" v-permission="'ssl-task.delete'">删除</a-button>
                </a-popconfirm>
              </a-space>
            </template>
          </a-table-column>
        </template>
      </a-table>
    </a-card>

    <!-- 签发弹窗：表单模式 → 提交后切换为过程进度 -->
    <a-modal
      v-model:visible="issueVisible"
      :title="issueTaskId ? '签发进度' : '签发 SSL 证书'"
      :width="issueTaskId ? 700 : 560"
      :mask-closable="false"
      :footer="!issueTaskId"
      @before-ok="handleIssueOk"
      @close="closeIssue"
      :ok-loading="issuing"
      ok-text="开始签发（后台执行）"
    >
      <a-form v-if="!issueTaskId" :model="issueForm" class="form-issue">
        <a-form-item label="域名" required help="多个域名用逗号或换行分隔，支持通配符（如 *.haoduoya.cc）">
          <a-textarea
            v-model="issueForm.domains"
            :auto-size="{ minRows: 2, maxRows: 5 }"
            placeholder="haoduoya.cc, *.haoduoya.cc"
          />
        </a-form-item>
        <a-form-item label="CA 环境" required help="staging 用于验证流程， production 为正式证书（有频率限制）">
          <a-radio-group v-model="issueForm.ca">
            <a-radio value="staging">staging（测试）</a-radio>
            <a-radio value="production">production（正式）</a-radio>
          </a-radio-group>
        </a-form-item>
        <a-form-item label="DNS 提供商" required help="从已配置的 DNS 凭据中选择，凭据在「DNS 凭据」菜单维护">
          <a-select
            v-model="issueForm.dnsAccountId"
            :options="dnsAccountOptions"
            :placeholder="dnsAccounts.length ? '选择 DNS 凭据' : '暂无 DNS 凭据，请在「DNS 凭据」菜单新建'"
          />
        </a-form-item>
      </a-form>
      <div v-else>
        <a-steps :current="stepIndex" :status="stepsStatus" size="small" label-placement="vertical" class="issue-steps">
          <a-step v-for="s in ISSUE_STEPS" :key="s" :title="s" />
        </a-steps>
        <div class="ssl-issue-log">
          <div v-if="!progressLog.length" class="ssl-issue-log-line">排队中，等待签发进程处理…</div>
          <div v-for="(l, i) in progressLog" :key="i" class="ssl-issue-log-line">
            <span class="ssl-issue-log-time">{{ l.time }}</span>
            <span :class="{ 'ssl-issue-log-err': l.stage === 'failed' }">{{ l.message }}</span>
          </div>
        </div>
        <a-space v-if="progressTask?.status === 'issued'" style="margin-top: 12px">
          <a-button type="primary" size="small" @click="download(progressTask.id, 'fullchain')">下载证书</a-button>
          <a-button size="small" @click="download(progressTask.id, 'privkey')">下载私钥</a-button>
          <a-button size="small" @click="closeIssue">完成</a-button>
        </a-space>
      </div>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { computed, onMounted, onUnmounted, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import BaseApi from '@/api/base';
  import { getToken } from '@/utils/auth';
  import { useUserStore } from '@/store';

  const userStore = useUserStore();
  const taskApi = new BaseApi('SslTask');
  const dnsApi = new BaseApi('DnsAccount');

  const loading = ref(false);
  const rows = ref<any[]>([]);
  const issueVisible = ref(false);
  const issuing = ref(false);
  const issueForm = ref<{ domains: string; ca: string; dnsAccountId?: number }>({ domains: '', ca: 'staging' });

  function parseDomains(record: any): string[] {
    try {
      return JSON.parse(record.domains);
    } catch (e) {
      return [];
    }
  }

  async function load() {
    loading.value = true;
    try {
      const res = await taskApi.getRows(1, {}, 100);
      rows.value = res.data?.data ?? [];
    } finally {
      loading.value = false;
    }
  }

  function openIssue() {
    // 默认选中唯一凭据，免去每次手选
    issueForm.value = { domains: '', ca: 'staging', dnsAccountId: dnsAccounts.value.length === 1 ? dnsAccounts.value[0].id : undefined };
    issueVisible.value = true;
  }

  // Arco 弹窗点 OK 默认自动关闭；签发需要留在弹窗内看进度，故拦截关闭，由提交逻辑自行控制
  function handleIssueOk(done: (closed: boolean) => void) {
    submitIssue();
  }

  async function submitIssue() {
    const domains = issueForm.value.domains
      .split(/[,;\n]/)
      .map((d) => d.trim())
      .filter(Boolean);
    if (domains.length === 0) {
      Message.error('请填写域名');
      return;
    }
    if (!issueForm.value.dnsAccountId) {
      Message.error('请选择 DNS 凭据');
      return;
    }
    issuing.value = true;
    try {
      // 下单即时返回，签发由后台进程执行；弹窗切换为过程进度轮询
      const res: any = await axios.post(
        '/ssl/order',
        { domains: domains.join(','), ca: issueForm.value.ca, dns_account_id: issueForm.value.dnsAccountId },
        {
          baseURL: import.meta.env.VITE_API_BASE_URL as string,
          headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        }
      );
      if (res.code === '0') {
        startPolling(res.data.id);
      } else {
        Message.error(res.message || '下单失败');
      }
    } catch (e: any) {
      Message.error(e?.response?.data?.message ?? e?.message ?? '下单失败');
    } finally {
      issuing.value = false;
    }
  }

  // ---------- 签发进度轮询 ----------
  const ISSUE_STEPS = ['创建订单', '添加 DNS 记录', '等待 DNS 生效', '挑战验证', '签发证书'];
  const STAGE_INDEX: Record<string, number> = { order: 0, dns: 1, dns_wait: 2, challenge: 3, finalize: 4 };
  const issueTaskId = ref<number | null>(null);
  const progressTask = ref<any>(null);
  let pollTimer: number | undefined;

  const stepIndex = computed(() => {
    if (progressTask.value?.status === 'issued') {
      return ISSUE_STEPS.length;
    }
    return STAGE_INDEX[progressTask.value?.stage ?? ''] ?? 0;
  });
  const stepsStatus = computed(() => (progressTask.value?.status === 'failed' ? 'error' : 'process'));
  const progressLog = computed<any[]>(() => {
    try {
      return JSON.parse(progressTask.value?.progress ?? '[]');
    } catch {
      return [];
    }
  });

  function startPolling(id: number) {
    issueTaskId.value = id;
    progressTask.value = null;
    stopPolling();
    const poll = async () => {
      const res = await taskApi.getItem(id);
      if (res.code === '0' && res.data) {
        progressTask.value = res.data;
        if (res.data.status === 'issued' || res.data.status === 'failed') {
          stopPolling();
          load();
        }
      }
    };
    void poll();
    pollTimer = window.setInterval(poll, 2000);
  }

  // 从任务列表重开进度视图（关闭弹窗不影响后台任务，随时可恢复查看）
  function openProgress(id: number) {
    issueVisible.value = true;
    startPolling(id);
  }

  function stopPolling() {
    if (pollTimer) {
      clearInterval(pollTimer);
      pollTimer = undefined;
    }
  }

  function closeIssue() {
    stopPolling();
    issueTaskId.value = null;
    progressTask.value = null;
    issueVisible.value = false;
  }

  onUnmounted(stopPolling);

  async function download(id: number, type: 'fullchain' | 'privkey') {
    try {
      const res = await axios.get(`/ssl/download/${id}/${type}`, {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        responseType: 'blob',
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
      });
      const url = URL.createObjectURL(res.data as Blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `${type}.pem`;
      a.click();
      URL.revokeObjectURL(url);
    } catch (e: any) {
      Message.error(e?.response?.data?.message ?? e?.message ?? '下载失败');
    }
  }

  async function deleteTask(id: number) {
    const res = await taskApi.deleteItem(id);
    if (res.code === '0') {
      Message.success('已删除');
      load();
    } else {
      Message.error(res.message);
    }
  }

  // ---------- DNS 凭据（签发表单下拉的只读数据源，CRUD 在「DNS 凭据」页） ----------
  const dnsAccounts = ref<any[]>([]);

  function providerName(provider: string): string {
    return provider === 'tencent' ? '腾讯云 DNS' : '阿里云 DNS';
  }

  const dnsAccountOptions = computed(() =>
    dnsAccounts.value.map((a) => ({ label: `${a.title}（${providerName(a.provider)}）`, value: a.id }))
  );

  async function loadDnsAccounts() {
    const res = await dnsApi.getRows(1, {}, 100);
    dnsAccounts.value = res.data?.data ?? [];
  }

  onMounted(() => {
    load();
    loadDnsAccounts();
  });
</script>

<style scoped>
  .ssl-issue-log {
    margin-top: 14px;
    max-height: 200px;
    overflow-y: auto;
    padding: 10px 12px;
    background: var(--color-fill-2);
    border-radius: 4px;
    font-size: 12px;
    line-height: 22px;
  }
  .ssl-issue-log-time {
    color: var(--color-text-3);
    margin-right: 10px;
    font-family: monospace;
  }
  .ssl-issue-log-err {
    color: rgb(var(--red-6));
  }
</style>

<style scoped>
  .form-issue :deep(.arco-form-item-label-col) {
    flex: 0 0 auto;
    width: 110px;
    max-width: 110px;
  }
  .form-issue :deep(.arco-form-item-wrapper-col) {
    flex: 1 1 auto;
    width: auto;
    max-width: 100%;
  }
  .form-issue :deep(.arco-form-item-label-col),
  .form-issue :deep(.arco-form-item-wrapper-col) {
    flex-basis: auto;
  }
</style>

<style scoped>
  .issue-steps :deep(.arco-steps-item-title) {
    font-size: 12px;
  }
  .issue-steps :deep(.arco-steps) {
    max-width: 100%;
  }
  .issue-steps :deep(.arco-steps-item) {
    min-width: 0;
    margin-right: 0;
  }
  .issue-steps :deep(.arco-steps-item:not(:last-child)) .arco-steps-item-tail {
    margin-right: 6px;
  }
</style>
