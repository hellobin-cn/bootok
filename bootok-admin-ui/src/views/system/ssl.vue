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

    <!-- 部署目标：签发/续期成功后自动投递证书并触发 reload -->
    <a-card :bordered="false" title="部署目标" style="margin-top: 10px">
      <template #extra>
        <a-button type="primary" @click="openDeploy()" v-permission="'ssl-deploy.create'">
          <template #icon><icon-plus /></template>
          新建目标
        </a-button>
      </template>

      <a-table :data="deploys" :loading="deployLoading" :pagination="false">
        <template #columns>
          <a-table-column title="ID" data-index="id" :width="70" />
          <a-table-column title="名称" data-index="title" :width="150" />
          <a-table-column title="绑定域名" data-index="domain" :width="180" />
          <a-table-column title="模式" :width="130" align="center">
            <template #cell="{ record }">
              <a-tag v-if="record.channel === 'cdn'" color="orange">{{ record.mode === 'tencent' ? '腾讯云 CDN' : '阿里云 CDN' }}</a-tag>
              <a-tag v-else-if="record.mode === 'remote'" color="purple">远程 SSH</a-tag>
              <a-tag v-else color="green">本机</a-tag>
            </template>
          </a-table-column>
          <a-table-column title="最近部署" :width="120" align="center">
            <template #cell="{ record }">
              <a-tag v-if="record.lastStatus === 'success'" color="green">成功</a-tag>
              <a-tooltip v-else-if="record.lastStatus === 'failed'" :content="record.lastError">
                <a-tag color="red">失败</a-tag>
              </a-tooltip>
              <a-tag v-else color="gray">从未</a-tag>
            </template>
          </a-table-column>
          <a-table-column title="部署时间" :width="170">
            <template #cell="{ record }">{{ record.lastDeployedAt ?? '-' }}</template>
          </a-table-column>
          <a-table-column title="操作" :width="340">
            <template #cell="{ record }">
              <a-space>
                <a-switch
                  :model-value="record.enabled"
                  size="small"
                  @change="(v: any) => toggleDeploy(record, v)"
                />
                <a-button
                  size="mini" type="outline"
                  :loading="deployingId === record.id"
                  @click="runDeploy(record.id)"
                >立即部署</a-button>
                <a-button
                  size="mini"
                  :loading="testingId === record.id"
                  @click="testDeploy(record.id)"
                >测试</a-button>
                <a-button size="mini" type="outline" @click="openDeploy(record)" v-permission="'ssl-deploy.edit'">编辑</a-button>
                <a-popconfirm content="确认删除该部署目标？" type="warning" @ok="deleteDeploy(record.id)">
                  <a-button size="mini" status="danger" v-permission="'ssl-deploy.delete'">删除</a-button>
                </a-popconfirm>
              </a-space>
            </template>
          </a-table-column>
        </template>
      </a-table>
    </a-card>

    <!-- DNS 凭据：签发/续期证书所用服务商鉴权账号 -->
    <a-card :bordered="false" title="DNS 凭据" style="margin-top: 10px">
      <template #extra>
        <a-button type="primary" @click="openDns()" v-permission="'dns-account.create'">
          <template #icon><icon-plus /></template>
          新建凭据
        </a-button>
      </template>

      <a-table :data="dnsAccounts" :loading="dnsLoading" :pagination="false">
        <template #columns>
          <a-table-column title="ID" data-index="id" :width="70" />
          <a-table-column title="名称" data-index="title" :width="200" />
          <a-table-column title="服务商" :width="160">
            <template #cell="{ record }">
              <a-tag>{{ providerName(record.provider) }}</a-tag>
            </template>
          </a-table-column>
          <a-table-column title="操作" :width="230">
            <template #cell="{ record }">
              <a-space>
                <a-button size="mini" :loading="dnsTestingId === record.id" @click="testDns(record.id)">测试</a-button>
                <a-button size="mini" type="outline" @click="openDns(record)" v-permission="'dns-account.edit'">编辑</a-button>
                <a-popconfirm content="确认删除该凭据？" type="warning" @ok="deleteDns(record.id)">
                  <a-button size="mini" status="danger" v-permission="'dns-account.delete'">删除</a-button>
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
      :width="issueTaskId ? 620 : 560"
      :mask-closable="false"
      :footer="!issueTaskId"
      @before-ok="handleIssueOk"
      @close="closeIssue"
      :ok-loading="issuing"
      ok-text="开始签发（后台执行）"
    >
      <a-form v-if="!issueTaskId" :model="issueForm" :label-width="110">
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
        <a-form-item label="DNS 提供商" required help="从已配置的 DNS 凭据中选择，凭据在页面下方维护">
          <a-select
            v-model="issueForm.dnsAccountId"
            :options="dnsAccountOptions"
            :placeholder="dnsAccounts.length ? '选择 DNS 凭据' : '暂无 DNS 凭据，请在下方新建'"
          />
        </a-form-item>
      </a-form>
      <div v-else>
        <a-steps :current="stepIndex" :status="stepsStatus" size="small">
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

    <!-- DNS 凭据编辑弹窗 -->
    <a-modal
      v-model:visible="dnsVisible"
      :title="dnsForm.id ? '编辑 DNS 凭据' : '新建 DNS 凭据'"
      :width="560"
      :mask-closable="false"
      @ok="submitDns"
      :ok-loading="dnsSaving"
    >
      <a-form :model="dnsForm" :label-width="130">
        <a-form-item label="名称" required>
          <a-input v-model="dnsForm.title" placeholder="如：阿里云-主账号" />
        </a-form-item>
        <a-form-item label="服务商" required>
          <a-radio-group v-model="dnsForm.provider" :disabled="!!dnsForm.id">
            <a-radio value="aliyun">阿里云 DNS</a-radio>
            <a-radio value="tencent">腾讯云 DNS</a-radio>
          </a-radio-group>
        </a-form-item>
        <template v-if="dnsForm.provider === 'aliyun'">
          <a-form-item label="AccessKey ID" required>
            <a-input v-model="dnsForm.accessKeyId" placeholder="AccessKey ID" />
          </a-form-item>
          <a-form-item label="AccessKey Secret" required>
            <a-input-password v-model="dnsForm.accessKeySecret" placeholder="AccessKey Secret" />
          </a-form-item>
        </template>
        <template v-else>
          <a-form-item label="SecretId" required>
            <a-input v-model="dnsForm.secretId" placeholder="腾讯云 API 密钥 SecretId" />
          </a-form-item>
          <a-form-item label="SecretKey" required>
            <a-input-password v-model="dnsForm.secretKey" placeholder="腾讯云 API 密钥 SecretKey" />
          </a-form-item>
        </template>
      </a-form>
    </a-modal>

    <!-- 部署目标编辑弹窗 -->
    <a-modal
      v-model:visible="deployVisible"
      :title="deployForm.id ? '编辑部署目标' : '新建部署目标'"
      :width="620"
      :mask-closable="false"
      @ok="submitDeploy"
      :ok-loading="deploySaving"
    >
      <a-form :model="deployForm" :label-width="110">
        <a-form-item label="名称" required>
          <a-input v-model="deployForm.title" placeholder="如：web-01 nginx" />
        </a-form-item>
        <a-form-item label="绑定域名" required help="从已签发证书的域名中选择，部署时证书与私钥取自对应任务">
          <a-select
            v-model="deployForm.domain"
            :options="deployDomainOptions"
            :placeholder="certDomainOptions.length ? '选择已签发证书的域名' : '暂无已签发证书，请先签发'"
            allow-search
          />
        </a-form-item>
        <a-form-item label="部署通道" required help="SSH：落盘并 reload；云 CDN：证书直推 CDN 域名 HTTPS 配置">
          <a-select v-model="deployForm.channel" :disabled="!!deployForm.id" @change="onChannelChange">
            <a-option value="ssh">SSH 部署（本机 / 远程主机）</a-option>
            <a-option value="cdn">云 CDN（HTTPS 证书直推）</a-option>
          </a-select>
        </a-form-item>

        <template v-if="deployForm.channel === 'cdn'">
          <a-form-item label="CDN 服务商" required>
            <a-select v-model="deployForm.mode" :disabled="!!deployForm.id">
              <a-option value="aliyun">阿里云 CDN</a-option>
              <a-option value="tencent">腾讯云 CDN</a-option>
            </a-select>
          </a-form-item>
          <a-form-item label="加速域名" required help="已接入 CDN 的域名，HTTPS 证书将配置到它上面">
            <a-input v-model="deployForm.config.cdnDomain" placeholder="cdn.example.com" />
          </a-form-item>
          <template v-if="deployForm.mode === 'aliyun'">
            <a-form-item label="AccessKey ID" required>
              <a-input v-model="deployForm.config.accessKeyId" />
            </a-form-item>
            <a-form-item label="AccessKey Secret" required>
              <a-input-password v-model="deployForm.config.accessKeySecret" />
            </a-form-item>
          </template>
          <template v-else>
            <a-form-item label="SecretId" required>
              <a-input v-model="deployForm.config.secretId" />
            </a-form-item>
            <a-form-item label="SecretKey" required>
              <a-input-password v-model="deployForm.config.secretKey" />
            </a-form-item>
          </template>
        </template>

        <template v-else>
          <a-form-item label="部署模式" required help="本机：直接落盘执行；远程：SSH 免密登录">
            <a-radio-group v-model="deployForm.mode">
              <a-radio value="local">本机</a-radio>
              <a-radio value="remote">远程 SSH</a-radio>
            </a-radio-group>
          </a-form-item>
          <template v-if="deployForm.mode === 'remote'">
            <a-form-item label="主机" required>
              <a-input v-model="deployForm.config.host" placeholder="IP 或主机名" />
            </a-form-item>
            <a-form-item label="端口">
              <a-input-number v-model="deployForm.config.port" :min="1" :max="65535" />
            </a-form-item>
            <a-form-item label="用户名" required>
              <a-input v-model="deployForm.config.username" placeholder="root" />
            </a-form-item>
            <a-form-item label="私钥" required help="OpenSSH/PEM 私钥；公钥需追加到目标机 ~/.ssh/authorized_keys">
              <a-textarea
                v-model="deployForm.config.privateKey"
                :auto-size="{ minRows: 3, maxRows: 8 }"
                placeholder="-----BEGIN PRIVATE KEY-----"
              />
            </a-form-item>
          </template>
          <a-form-item label="证书路径" required :help="deployForm.mode === 'local' ? '本机绝对路径' : '远端绝对路径'">
            <a-input v-model="deployForm.config.certPath" placeholder="/etc/nginx/ssl/fullchain.pem" />
          </a-form-item>
          <a-form-item label="私钥路径" required>
            <a-input v-model="deployForm.config.keyPath" placeholder="/etc/nginx/ssl/privkey.pem" />
          </a-form-item>
          <a-form-item label="reload 命令" required help="证书写入后执行，如 systemctl reload nginx">
            <a-input v-model="deployForm.config.reloadCmd" placeholder="systemctl reload nginx" />
          </a-form-item>
        </template>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { computed, onMounted, onUnmounted, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import BaseApi from '@/api/base';
  import field from '@/utils/field';
  import { getToken } from '@/utils/auth';
  import { useUserStore } from '@/store';

  const userStore = useUserStore();
  const taskApi = new BaseApi('SslTask');

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
          loadDeploys();
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

  // ---------- 部署目标 ----------
  const deployApi = new BaseApi('SslDeploy');

  const deploys = ref<any[]>([]);
  const deployLoading = ref(false);
  const deployVisible = ref(false);
  const deploySaving = ref(false);
  const deployingId = ref<number | null>(null);
  const testingId = ref<number | null>(null);
  const deployForm = ref(emptyDeployForm());

  function emptyDeployForm() {
    return {
      id: 0,
      title: '',
      domain: '',
      channel: 'ssh',
      mode: 'local',
      config: {
        host: '', port: 22, username: '', privateKey: '', certPath: '', keyPath: '', reloadCmd: '',
        cdnDomain: '', accessKeyId: '', accessKeySecret: '', secretId: '', secretKey: '',
      },
    };
  }

  // 绑定域名只能从已签发证书中选择（*.x 归并为基础域名），保证绑定的一定是有真实证书的域名
  const certDomainOptions = computed(() => {
    const set = new Set<string>();
    for (const r of rows.value) {
      if (r.status !== 'issued') continue;
      for (const d of parseDomains(r)) {
        set.add(d.replace(/^\*\./, ''));
      }
    }
    return Array.from(set).map((d) => ({ label: d, value: d }));
  });

  // 编辑时旧绑定域名可能已不在选项中（证书被删），补进选项避免显示空
  const deployDomainOptions = computed(() => {
    const opts = [...certDomainOptions.value];
    const current = deployForm.value.domain;
    if (current && !opts.some((o) => o.value === current)) {
      opts.unshift({ label: current, value: current });
    }
    return opts;
  });

  async function loadDeploys() {
    deployLoading.value = true;
    try {
      const res = await deployApi.getRows(1, {}, 100);
      deploys.value = res.data?.data ?? [];
    } finally {
      deployLoading.value = false;
    }
  }

  // 通道切换时把 mode 归位到该通道的合法值（下拉显示残留 local/aliyun 会误导）
  function onChannelChange(channel: string | number | boolean | Record<string, any> | (string | number | boolean | Record<string, any>)[]) {
    const c = String(channel);
    if (c === 'cdn' && !['aliyun', 'tencent'].includes(deployForm.value.mode)) {
      deployForm.value.mode = 'aliyun';
    }
    if (c === 'ssh' && !['local', 'remote'].includes(deployForm.value.mode)) {
      deployForm.value.mode = 'local';
    }
  }

  function openDeploy(record?: any) {
    if (record) {
      // BaseApi 响应已转驼峰：config.cert_path => config.certPath
      deployForm.value = {
        id: record.id,
        title: record.title,
        domain: record.domain,
        channel: record.channel ?? 'ssh',
        mode: record.mode,
        config: Object.assign(emptyDeployForm().config, record.config ?? {}),
      };
    } else {
      deployForm.value = emptyDeployForm();
    }
    deployVisible.value = true;
  }

  async function submitDeploy() {
    const f = deployForm.value;
    if (!f.title.trim() || !f.domain.trim()) {
      Message.error('请填写名称与绑定域名');
      return;
    }
    deploySaving.value = true;
    try {
      // raw axios（BaseApi 业务错误时 promise 不落定，ok-loading 会永远转圈）；
      // config 驼峰键转下划线（与 BaseApi 提交语义一致；hump2underline 原地转换无返回值）
      // 部署方式四选一（mode），channel 由 mode 推导：CDN 厂商 → cdn，其余 → ssh
      const payload: any = {
        title: f.title,
        domain: f.domain,
        channel: f.mode === 'aliyun' || f.mode === 'tencent' ? 'cdn' : 'ssh',
        mode: f.mode,
        config: f.config,
      };
      field.hump2underline(payload);
      const res: any = await axios.request({
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        url: f.id ? `/auto/ssl-deploy/${f.id}` : '/auto/ssl-deploy',
        method: f.id ? 'PUT' : 'POST',
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        data: payload,
      });
      if (res.code === '0') {
        Message.success(f.id ? '已更新' : '已创建');
        deployVisible.value = false;
        loadDeploys();
      }
    } catch (e: any) {
      Message.error(e?.message ?? '保存失败');
    } finally {
      deploySaving.value = false;
    }
  }

  async function toggleDeploy(record: any, enabled: boolean) {
    const res = await deployApi.updateItem(record.id, { enabled });
    if (res.code === '0') {
      record.enabled = enabled;
    }
  }

  async function runDeploy(id: number) {
    deployingId.value = id;
    try {
      const res: any = await axios.post(`/ssl/deploy/${id}/run`, {}, {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        timeout: 30000,
      });
      if (res.code === '0') {
        Message.success('部署成功');
        loadDeploys();
      }
    } catch (e: any) {
      Message.error(e?.message ?? '部署失败');
    } finally {
      deployingId.value = null;
    }
  }

  async function testDeploy(id: number) {
    testingId.value = id;
    try {
      const res: any = await axios.post(`/ssl/deploy/${id}/test`, {}, {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        timeout: 30000,
      });
      if (res.code === '0') {
        Message.success(res.data?.message || '连通正常');
      }
    } catch (e: any) {
      Message.error(e?.message ?? '测试失败');
    } finally {
      testingId.value = null;
    }
  }

  async function deleteDeploy(id: number) {
    const res = await deployApi.deleteItem(id);
    if (res.code === '0') {
      Message.success('已删除');
      loadDeploys();
    }
  }

  // ---------- DNS 凭据 ----------
  const dnsApi = new BaseApi('DnsAccount');

  const dnsAccounts = ref<any[]>([]);
  const dnsLoading = ref(false);
  const dnsVisible = ref(false);
  const dnsSaving = ref(false);
  const dnsTestingId = ref<number | null>(null);
  const dnsForm = ref(emptyDnsForm());

  function emptyDnsForm() {
    return { id: 0, title: '', provider: 'aliyun', accessKeyId: '', accessKeySecret: '', secretId: '', secretKey: '' };
  }

  function providerName(provider: string): string {
    return provider === 'tencent' ? '腾讯云 DNS' : '阿里云 DNS';
  }

  const dnsAccountOptions = computed(() =>
    dnsAccounts.value.map((a) => ({ label: `${a.title}（${providerName(a.provider)}）`, value: a.id }))
  );

  async function loadDnsAccounts() {
    dnsLoading.value = true;
    try {
      const res = await dnsApi.getRows(1, {}, 100);
      dnsAccounts.value = res.data?.data ?? [];
    } finally {
      dnsLoading.value = false;
    }
  }

  function openDns(record?: any) {
    if (record) {
      const config = record.config ?? {};
      dnsForm.value = {
        id: record.id,
        title: record.title,
        provider: record.provider,
        accessKeyId: config.accessKeyId ?? '',
        accessKeySecret: config.accessKeySecret ?? '',
        secretId: config.secretId ?? '',
        secretKey: config.secretKey ?? '',
      };
    } else {
      dnsForm.value = emptyDnsForm();
    }
    dnsVisible.value = true;
  }

  async function submitDns() {
    const f = dnsForm.value;
    if (!f.title.trim()) {
      Message.error('请填写名称');
      return;
    }
    const config = f.provider === 'tencent'
      ? { secret_id: f.secretId.trim(), secret_key: f.secretKey.trim() }
      : { access_key_id: f.accessKeyId.trim(), access_key_secret: f.accessKeySecret.trim() };
    dnsSaving.value = true;
    try {
      // raw axios（BaseApi 业务错误时 promise 不落定，ok-loading 会永远转圈）
      const res: any = await axios.request({
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        url: f.id ? `/auto/dns-account/${f.id}` : '/auto/dns-account',
        method: f.id ? 'PUT' : 'POST',
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        data: (() => { const p: any = { title: f.title, provider: f.provider, config }; field.hump2underline(p); return p; })(),
      });
      if (res.code === '0') {
        Message.success(f.id ? '已更新' : '已创建');
        dnsVisible.value = false;
        loadDnsAccounts();
      }
    } catch (e: any) {
      Message.error(e?.message ?? '保存失败');
    } finally {
      dnsSaving.value = false;
    }
  }

  async function deleteDns(id: number) {
    const res = await dnsApi.deleteItem(id);
    if (res.code === '0') {
      Message.success('已删除');
      loadDnsAccounts();
    }
  }

  async function testDns(id: number) {
    dnsTestingId.value = id;
    try {
      const res: any = await axios.post(`/ssl/dns-account/${id}/test`, {}, {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        timeout: 30000,
      });
      if (res.code === '0') {
        Message.success(res.data?.message || '凭据有效');
      }
    } catch (e: any) {
      Message.error(e?.message ?? '测试失败');
    } finally {
      dnsTestingId.value = null;
    }
  }

  onMounted(() => {
    load();
    loadDeploys();
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
