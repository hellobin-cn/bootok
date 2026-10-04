<template>
  <a-card :bordered="false" title="邮件渠道">
    <template #extra>
      <a-button type="primary" @click="openChannel()" v-permission="'mail-channel.create'">
        <template #icon><icon-plus /></template>
        新建渠道
      </a-button>
    </template>

    <a-table :data="channels" :loading="channelLoading" :pagination="false">
      <template #columns>
        <a-table-column title="ID" data-index="id" :width="70" />
        <a-table-column title="名称" data-index="title" :width="180">
          <template #cell="{ record }">
            {{ record.title }}
            <a-tag v-if="record.isDefault" color="green">默认</a-tag>
          </template>
        </a-table-column>
        <a-table-column title="SMTP" :width="240">
          <template #cell="{ record }">
            <span>{{ record.config?.host }}:{{ record.config?.port }}</span>
            <a-tag size="small" style="margin-left: 8px">{{ encryptName(record.config?.encryption) }}</a-tag>
          </template>
        </a-table-column>
        <a-table-column title="发件人" :width="220">
          <template #cell="{ record }">
            <span v-if="record.config?.fromAddress">{{ record.config.fromAddress }}</span>
            <span v-else style="color: var(--color-text-3)">{{ record.config?.username }}</span>
          </template>
        </a-table-column>
        <a-table-column title="操作" :width="240">
          <template #cell="{ record }">
            <a-space>
              <a-button size="mini" :loading="channelTestingId === record.id" @click="testChannel(record.id)">测试</a-button>
              <a-button size="mini" type="outline" @click="openChannel(record)" v-permission="'mail-channel.edit'">编辑</a-button>
              <a-popconfirm content="确认删除该邮件渠道？" type="warning" @ok="deleteChannel(record.id)">
                <a-button size="mini" status="danger" v-permission="'mail-channel.delete'">删除</a-button>
              </a-popconfirm>
            </a-space>
          </template>
        </a-table-column>
      </template>
    </a-table>
  </a-card>

  <!-- 渠道编辑弹窗 -->
  <a-modal
    v-model:visible="channelVisible"
    :title="channelForm.id ? '编辑邮件渠道' : '新建邮件渠道'"
    :width="560"
    :mask-closable="false"
    @before-ok="handleChannelOk"
  >
    <a-form :model="channelForm" class="form-channel" label-align="right" auto-label-width>
      <a-form-item label="名称" required>
        <a-input v-model="channelForm.title" placeholder="如：阿里云邮件推送 / 腾讯企业邮" />
      </a-form-item>
      <a-form-item label="SMTP 服务器" required>
        <a-input v-model="channelForm.config.host" placeholder="如 smtpdm.aliyun.com" />
      </a-form-item>
      <a-form-item label="端口" required>
        <a-input-number v-model="channelForm.config.port" :min="1" :max="65535" placeholder="465" style="width: 160px" />
      </a-form-item>
      <a-form-item label="加密方式" required>
        <a-radio-group v-model="channelForm.config.encryption">
          <a-radio value="ssl">SSL（465）</a-radio>
          <a-radio value="tls">STARTTLS（587）</a-radio>
          <a-radio value="none">不加密（25）</a-radio>
        </a-radio-group>
      </a-form-item>
      <a-form-item label="SMTP 账号" help="部分服务商免认证可不填">
        <a-input v-model="channelForm.config.username" />
      </a-form-item>
      <a-form-item label="密码/授权码">
        <a-input-password v-model="channelForm.config.password" />
      </a-form-item>
      <a-form-item label="发件人地址" required help="多数服务商要求与 SMTP 账号一致，且需已验证">
        <a-input v-model="channelForm.config.fromAddress" placeholder="noreply@example.com" />
      </a-form-item>
      <a-form-item label="发件人名称" help="留空取站点名称">
        <a-input v-model="channelForm.config.fromName" />
      </a-form-item>
      <a-form-item label="默认渠道" help="业务发送未指定渠道时使用默认渠道">
        <a-switch v-model="channelForm.isDefault" />
      </a-form-item>

      <a-form-item label=" " help="实发一封测试邮件验证配置（无需先保存）">
        <a-space>
          <a-input v-model="channelForm.testTo" placeholder="测试收件邮箱，留空发给自己" style="width: 240px" />
          <a-button :loading="channelTestRunning" @click="testChannelConfig">测试当前配置</a-button>
        </a-space>
      </a-form-item>
    </a-form>
  </a-modal>
</template>

<script lang="ts" setup>
  import { onMounted, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import BaseApi from '@/api/base';
  import field from '@/utils/field';
  import { getToken } from '@/utils/auth';

  const channelApi = new BaseApi('MailChannel');

  const channels = ref<any[]>([]);
  const channelLoading = ref(false);
  const channelVisible = ref(false);
  const channelSaving = ref(false);
  const channelForm = ref(emptyChannelForm());

  function emptyChannelForm() {
    return {
      id: 0,
      title: '',
      isDefault: false,
      config: {
        host: '',
        port: 465,
        encryption: 'ssl',
        username: '',
        password: '',
        fromAddress: '',
        fromName: '',
      },
      testTo: '',
    };
  }

  function encryptName(encryption: string): string {
    return { ssl: 'SSL', tls: 'STARTTLS', none: '不加密' }[encryption] ?? encryption;
  }

  async function loadChannels() {
    channelLoading.value = true;
    try {
      const res = await channelApi.getRows(1, {}, 100);
      channels.value = res.data?.data ?? [];
    } finally {
      channelLoading.value = false;
    }
  }

  function openChannel(record?: any) {
    if (record) {
      channelForm.value = {
        id: record.id,
        title: record.title,
        isDefault: !!record.isDefault,
        config: { ...emptyChannelForm().config, ...(record.config ?? {}) },
        testTo: '',
      };
    } else {
      channelForm.value = emptyChannelForm();
    }
    channelVisible.value = true;
  }

  // Arco 弹窗 OK 默认自动关闭，校验失败需要留在弹窗内，故拦截
  // 保存走 raw axios（BaseApi 业务错误时 promise 不落定，ok-loading 会永远转圈）
  async function handleChannelOk(done: (closed: boolean) => void) {
    const f = channelForm.value;
    if (!f.title.trim()) {
      Message.error('请填写名称');
      return;
    }
    if (!f.config.host.trim() || !f.config.fromAddress.trim()) {
      Message.error('请填写 SMTP 服务器与发件人地址');
      return;
    }
    channelSaving.value = true;
    try {
      // field.hump2underline 为原地转换无返回值（项目约定：先赋值再转换）
      const payload: any = { title: f.title, type: 'smtp', is_default: f.isDefault, config: f.config };
      field.hump2underline(payload);
      const res: any = await axios.request({
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        url: f.id ? `/auto/mail-channel/${f.id}` : '/auto/mail-channel',
        method: f.id ? 'PUT' : 'POST',
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        data: payload,
      });
      if (res.code === '0') {
        Message.success(f.id ? '已更新' : '已创建');
        done(true);
        loadChannels();
      }
    } catch (e: any) {
      Message.error(e?.message ?? '保存失败');
    } finally {
      channelSaving.value = false;
    }
  }

  async function deleteChannel(id: number) {
    const res = await channelApi.deleteItem(id);
    if (res.code === '0') {
      Message.success('已删除');
      loadChannels();
    }
  }

  // 行级测试：实发测试邮件到发件人自己
  const channelTestingId = ref<number | null>(null);

  async function testChannel(id: number) {
    channelTestingId.value = id;
    try {
      const res: any = await axios.post(`/mail/channel/${id}/test`, {}, {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        timeout: 30000,
      });
      if (res.code === '0') {
        Message.success(res.data?.message || '测试邮件已发送');
      }
    } catch (e: any) {
      Message.error(e?.message ?? '测试失败');
    } finally {
      channelTestingId.value = null;
    }
  }

  // 弹窗内测试「未保存」的表单配置
  const channelTestRunning = ref(false);

  async function testChannelConfig() {
    const f = channelForm.value;
    channelTestRunning.value = true;
    try {
      const payload: any = { type: 'smtp', config: f.config, to: f.testTo };
      field.hump2underline(payload);
      const res: any = await axios.post('/mail/channel/test', payload, {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        timeout: 30000,
      });
      if (res.code === '0') {
        Message.success(res.data?.message || '测试邮件已发送');
      }
    } catch (e: any) {
      Message.error(e?.message ?? '测试失败');
    } finally {
      channelTestRunning.value = false;
    }
  }

  onMounted(loadChannels);
</script>
