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
          <a-table-column title="到期时间" data-index="expires_at" :width="170" />
          <a-table-column title="操作" :width="230">
            <template #cell="{ record }">
              <a-space>
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

    <!-- 签发弹窗 -->
    <a-modal
      v-model:visible="issueVisible"
      title="签发 SSL 证书"
      :width="560"
      :mask-closable="false"
      @ok="submitIssue"
      :ok-loading="issuing"
      ok-text="开始签发（约 1-2 分钟）"
    >
      <a-form :model="issueForm" auto-label-width>
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
        <a-form-item label="DNS 提供商">
          <a-input value="阿里云 DNS（凭据在系统配置中维护）" disabled />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { onMounted, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import BaseApi from '@/api/base';
  import { getToken } from '@/utils/auth';
  import { useUserStore } from '@/store';

  const userStore = useUserStore();
  const taskApi = new BaseApi('SslTask');

  const loading = ref(false);
  const rows = ref<any[]>([]);
  const issueVisible = ref(false);
  const issuing = ref(false);
  const issueForm = ref({ domains: '', ca: 'staging' });

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
    issueForm.value = { domains: '', ca: 'staging' };
    issueVisible.value = true;
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
    issuing.value = true;
    try {
      // 签发为同步阻塞流程（60-120 秒），单独放宽超时
      const res = await axios.post(
        '/ssl/order',
        { domains: domains.join(','), ca: issueForm.value.ca },
        {
          baseURL: import.meta.env.VITE_API_BASE_URL as string,
          headers: { Authorization: `Bearer ${getToken() ?? ''}` },
          timeout: 180000,
        }
      );
      if (res.data.code === '0') {
        Message.success('证书签发成功');
        issueVisible.value = false;
        load();
      } else {
        Message.error(res.data.message || '签发失败');
      }
    } catch (e: any) {
      Message.error(e?.response?.data?.message ?? e?.message ?? '签发失败');
    } finally {
      issuing.value = false;
    }
  }

  async function download(id: number, type: 'fullchain' | 'privkey') {
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

  onMounted(load);
</script>
