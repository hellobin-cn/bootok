<template>
  <a-card :bordered="false" title="DNS 凭据">
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

  <!-- DNS 凭据编辑弹窗 -->
  <a-modal
    v-model:visible="dnsVisible"
    :title="dnsForm.id ? '编辑 DNS 凭据' : '新建 DNS 凭据'"
    :width="560"
    :mask-closable="false"
    @ok="submitDns"
    :ok-loading="dnsSaving"
  >
    <a-form :model="dnsForm" class="form-dns" label-align="right" auto-label-width>
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
</template>

<script lang="ts" setup>
  import { computed, onMounted, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import BaseApi from '@/api/base';
  import field from '@/utils/field';
  import { getToken } from '@/utils/auth';

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

  onMounted(loadDnsAccounts);
</script>

