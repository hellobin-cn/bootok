<template>
  <div>
    <!-- 存储渠道：附件的存储后端（local/oss/cos）与 CDN 绑定 -->
    <a-card :bordered="false" title="存储渠道">
      <template #extra>
        <a-button type="primary" @click="openChannel()" v-permission="'storage-channel.create'">
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
          <a-table-column title="类型" :width="140">
            <template #cell="{ record }">
              <a-tag>{{ typeName(record.type) }}</a-tag>
            </template>
          </a-table-column>
          <a-table-column title="CDN" :width="200">
            <template #cell="{ record }">
              <span v-if="record.config?.cdnDomain">
                {{ record.config.scheme === 'http' ? 'http://' : 'https://' }}{{ record.config.cdnDomain }}
              </span>
              <span v-else style="color: var(--color-text-3)">直连</span>
            </template>
          </a-table-column>
          <a-table-column title="操作" :width="240">
            <template #cell="{ record }">
              <a-space>
                <a-button size="mini" :loading="channelTestingId === record.id" @click="testChannel(record.id)">测试</a-button>
                <a-button size="mini" type="outline" @click="openChannel(record)" v-permission="'storage-channel.edit'">编辑</a-button>
                <a-popconfirm content="确认删除该渠道？已删除渠道上的附件将回退默认渠道访问" type="warning" @ok="deleteChannel(record.id)">
                  <a-button size="mini" status="danger" v-permission="'storage-channel.delete'">删除</a-button>
                </a-popconfirm>
              </a-space>
            </template>
          </a-table-column>
        </template>
      </a-table>
    </a-card>

    <!-- 附件列表 -->
    <crud
      style="margin-top: 10px"
      model-name="attachment"
      model-text="附件"
      key-name="id"
      :columns="columns"
      :filter="filter"
      :add-enable="false"
      :edit-enable="false"
      :delete-enable="false"
      :action-enable="false"
      :show-update-time="false"
      :show-create-time="false"
      :batch="false"
    >
      <template #search>
        <a-card :bordered="false" style="margin-bottom: 12px">
          <a-space>
            <a-input-search
              v-model="query.adminName"
              placeholder="搜索上传人"
              style="width: 200px"
              allow-clear
              @search="() => crudRef.getItems(1)"
              @clear="() => crudRef.getItems(1)"
            />
            <a-select v-model="query.ext" placeholder="类型" style="width: 120px" allow-clear @change="() => crudRef.getItems(1)">
              <a-option v-for="e in EXT_OPTIONS" :key="e" :value="e">{{ e }}</a-option>
            </a-select>
          </a-space>
        </a-card>
      </template>
      <template #path="{ record }">
        <a-space>
          <a-image
            v-if="isImage(record.ext)"
            :src="record.url"
            :width="44"
            :height="44"
            fit="cover"
            style="border-radius: 4px; vertical-align: middle"
          />
          <a-link :href="record.url" target="_blank">
            {{ record.originalName || record.path }}
          </a-link>
        </a-space>
      </template>
      <template #ext="{ record }">
        <a-tag size="small">{{ record.ext?.toUpperCase() || '-' }}</a-tag>
      </template>
      <template #size="{ record }">
        {{ formatSize(record.size) }}
      </template>
    </crud>

    <!-- 渠道编辑弹窗 -->
    <a-modal
      v-model:visible="channelVisible"
      :title="channelForm.id ? '编辑存储渠道' : '新建存储渠道'"
      :width="560"
      :mask-closable="false"
      @before-ok="handleChannelOk"
      :ok-loading="channelSaving"
    >
      <a-form :model="channelForm" class="form-channel">
        <a-form-item label="名称" required>
          <a-input v-model="channelForm.title" placeholder="如：本地存储 / 阿里云OSS" />
        </a-form-item>
        <a-form-item label="类型" required>
          <a-radio-group v-model="channelForm.type" :disabled="!!channelForm.id">
            <a-radio value="local">本地存储</a-radio>
            <a-radio value="oss">阿里云 OSS</a-radio>
            <a-radio value="cos">腾讯云 COS</a-radio>
          </a-radio-group>
        </a-form-item>

        <template v-if="channelForm.type === 'local'">
          <a-form-item label="存储根目录" help="相对项目根目录（默认 public）或绝对路径；public 外经 /storage 路由访问">
            <a-input v-model="channelForm.config.root" placeholder="public" />
          </a-form-item>
        </template>
        <template v-else-if="channelForm.type === 'oss'">
          <a-form-item label="Bucket" required>
            <a-input v-model="channelForm.config.bucket" />
          </a-form-item>
          <a-form-item label="Endpoint" required help="如 oss-cn-beijing.aliyuncs.com，内网可填内网 endpoint">
            <a-input v-model="channelForm.config.endpoint" />
          </a-form-item>
          <a-form-item label="AccessKey ID" required>
            <a-input v-model="channelForm.config.accessKeyId" />
          </a-form-item>
          <a-form-item label="AccessKey Secret" required>
            <a-input-password v-model="channelForm.config.accessKeySecret" />
          </a-form-item>
        </template>
        <template v-else>
          <a-form-item label="Bucket" required>
            <a-input v-model="channelForm.config.bucket" placeholder="如 mybucket-1250000000" />
          </a-form-item>
          <a-form-item label="地域" required help="如 ap-beijing">
            <a-input v-model="channelForm.config.region" />
          </a-form-item>
          <a-form-item label="SecretId" required>
            <a-input v-model="channelForm.config.secretId" />
          </a-form-item>
          <a-form-item label="SecretKey" required>
            <a-input-password v-model="channelForm.config.secretKey" />
          </a-form-item>
        </template>

        <a-form-item label="默认渠道" help="新上传的附件使用默认渠道">
          <a-switch v-model="channelForm.isDefault" />
        </a-form-item>

        <a-form-item label="CDN 加速" help="绑定后附件访问 URL 走 CDN 域名；留空直连存储">
          <a-input v-model="channelForm.config.cdnDomain" placeholder="cdn.example.com" />
        </a-form-item>
        <a-form-item v-if="channelForm.config.cdnDomain" label="CDN 协议" help="CDN 域名的 HTTPS / HTTP 访问协议">
          <a-radio-group v-model="channelForm.config.scheme">
            <a-radio value="https">HTTPS</a-radio>
            <a-radio value="http">HTTP</a-radio>
          </a-radio-group>
        </a-form-item>

        <a-form-item label=" " help="写读删一个探针对象，验证配置与权限（无需先保存）">
          <a-button :loading="channelTestRunning" @click="testChannelConfig">测试当前配置</a-button>
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { computed, onMounted, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import crud from '@/components/crud/index.vue';
  import BaseApi from '@/api/base';
  import field from '@/utils/field';
  import { getToken } from '@/utils/auth';
  import type { TableColumnData } from '@arco-design/web-vue';

  const crudRef = ref();
  const query = ref({ adminName: '', ext: undefined as string | undefined });

  const EXT_OPTIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'mp4', 'mp3', 'txt', 'csv', 'md'];
  const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

  function isImage(ext: string): boolean {
    return IMAGE_EXTS.includes((ext || '').toLowerCase());
  }

  const filter = computed(() => ({
    adminNameLike: query.value.adminName,
    ...(query.value.ext ? { ext: query.value.ext } : {}),
  }));

  const columns = ref<TableColumnData[]>([
    { title: '文件', slotName: 'path', ellipsis: true },
    { title: '类型', slotName: 'ext', width: 90, align: 'center' },
    { title: '大小', slotName: 'size', width: 100, align: 'center' },
    { title: '上传人', dataIndex: 'adminName', width: 120 },
    { title: '创建时间', dataIndex: 'createdAt', width: 180, align: 'center' },
  ]);

  function formatSize(bytes: number): string {
    if (!bytes) return '--';
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(2)} MB`;
  }

  // ---------- 存储渠道 ----------
  const channelApi = new BaseApi('StorageChannel');

  const channels = ref<any[]>([]);
  const channelLoading = ref(false);
  const channelVisible = ref(false);
  const channelSaving = ref(false);
  const channelForm = ref(emptyChannelForm());

  function emptyChannelForm() {
    return {
      id: 0,
      title: '',
      type: 'local',
      isDefault: false,
      config: {
        root: 'public',
        bucket: '',
        endpoint: '',
        region: '',
        accessKeyId: '',
        accessKeySecret: '',
        secretId: '',
        secretKey: '',
        cdnDomain: '',
        scheme: 'https',
      },
    };
  }

  function typeName(type: string): string {
    return { local: '本地存储', oss: '阿里云 OSS', cos: '腾讯云 COS' }[type] ?? type;
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
        type: record.type,
        isDefault: !!record.isDefault,
        config: { ...emptyChannelForm().config, ...(record.config ?? {}) },
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
    channelSaving.value = true;
    try {
      // field.hump2underline 为原地转换无返回值（项目约定：先赋值再转换）
      const payload: any = { title: f.title, type: f.type, is_default: f.isDefault, config: f.config };
      field.hump2underline(payload);
      const res: any = await axios.request({
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        url: f.id ? `/auto/storage-channel/${f.id}` : '/auto/storage-channel',
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

  // 配置自检：写读删探针对象，验证凭据/权限/网络（新建未保存的渠道先保存再测）
  const channelTestingId = ref<number | null>(null);

  async function testChannel(id: number) {
    channelTestingId.value = id;
    try {
      const res: any = await axios.post(`/storage/channel/${id}/test`, {}, {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        timeout: 30000,
      });
      if (res.code === '0') {
        Message.success(res.data?.message || '配置有效');
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
      const payload: any = { type: f.type, config: f.config };
      field.hump2underline(payload);
      const res: any = await axios.post('/storage/channel/test', payload, {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        timeout: 30000,
      });
      if (res.code === '0') {
        Message.success(res.data?.message || '配置有效');
      }
    } catch (e: any) {
      Message.error(e?.message ?? '测试失败');
    } finally {
      channelTestRunning.value = false;
    }
  }

  onMounted(loadChannels);
</script>

<style scoped></style>

<style scoped>
  .form-channel :deep(.arco-form-item-label-col) {
    flex: 0 0 130px;
    width: 130px;
    max-width: 130px;
  }
  .form-channel :deep(.arco-form-item-wrapper-col) {
    flex: 1 1 auto;
    width: auto;
    max-width: 100%;
  }
</style>
