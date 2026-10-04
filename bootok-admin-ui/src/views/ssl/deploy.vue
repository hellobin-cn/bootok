<template>
  <a-card :bordered="false" title="部署目标">
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

  <!-- 部署目标编辑弹窗 -->
  <a-modal
    v-model:visible="deployVisible"
    :title="deployForm.id ? '编辑部署目标' : '新建部署目标'"
    :width="620"
    :mask-closable="false"
    @ok="submitDeploy"
    :ok-loading="deploySaving"
  >
    <a-form :model="deployForm" class="form-deploy" label-align="right" auto-label-width>
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
</template>

<script lang="ts" setup>
  import { computed, onMounted, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import BaseApi from '@/api/base';
  import field from '@/utils/field';
  import { getToken } from '@/utils/auth';

  const deployApi = new BaseApi('SslDeploy');
  const taskApi = new BaseApi('SslTask');

  const deploys = ref<any[]>([]);
  const deployLoading = ref(false);
  const deployVisible = ref(false);
  const deploySaving = ref(false);
  const deployingId = ref<number | null>(null);
  const testingId = ref<number | null>(null);
  const deployForm = ref(emptyDeployForm());
  const issuedRows = ref<any[]>([]);

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

  function parseDomains(record: any): string[] {
    try {
      return JSON.parse(record.domains);
    } catch (e) {
      return [];
    }
  }

  // 绑定域名只能从已签发证书中选择（*.x 归并为基础域名），保证绑定的一定是有真实证书的域名
  const certDomainOptions = computed(() => {
    const set = new Set<string>();
    for (const r of issuedRows.value) {
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

  async function loadIssuedRows() {
    const res = await taskApi.getRows(1, {}, 100);
    issuedRows.value = res.data?.data ?? [];
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
      // channel 由 mode 推导：CDN 厂商 → cdn，其余 → ssh
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

  onMounted(() => {
    loadDeploys();
    loadIssuedRows();
  });
</script>

