<template>
  <div>
    <!-- 统计概览 -->
    <a-row :gutter="10">
      <a-col :span="8">
        <a-card :bordered="false">
          <a-statistic title="附件总数" :value="stats.total" style="text-align: center" />
        </a-card>
      </a-col>
      <a-col :span="8">
        <a-card :bordered="false">
          <a-statistic title="总大小" style="text-align: center"><template #default><span>{{ formatSize(stats.size) }}</span></template></a-statistic>
        </a-card>
      </a-col>
      <a-col :span="8">
        <a-card :bordered="false">
          <a-statistic title="本月新增" :value="stats.monthNew" style="text-align: center" />
        </a-card>
      </a-col>
    </a-row>

    <!-- 文件管理器：左目录树 + 右文件表 -->
    <a-card :bordered="false" style="margin-top: 10px">
      <template #extra>
        <a-space>
          <a-select
            v-model="currentChannelId"
            style="width: 200px"
            placeholder="存储渠道"
            @change="onChannelChange"
          >
            <a-option v-for="c in channels" :key="c.id" :value="c.id">
              {{ c.title }}
              <a-tag v-if="c.isDefault" color="green" style="margin-left: 4px">默认</a-tag>
            </a-option>
          </a-select>
          <a-upload :custom-request="(doUpload as any)" :show-file-list="false" multiple>
            <a-button type="primary" :loading="uploading">
              <template #icon><icon-upload /></template>
              上传到此目录
            </a-button>
          </a-upload>
          <a-button @click="openMkdir(null)">
            <template #icon><icon-folder-add /></template>
            新建目录
          </a-button>
          <a-button @click="refresh">
            <template #icon><icon-refresh /></template>
            刷新
          </a-button>
        </a-space>
      </template>

      <div style="display: flex; gap: 12px; height: 540px">
        <!-- 左：目录树 -->
        <div style="width: 260px; flex: 0 0 260px; overflow-y: auto; border-right: 1px solid var(--color-border-2); padding-right: 8px">
          <a-button
            long size="small" type="text"
            :style="currentFolderId === null ? 'color: var(--color-text-1); font-weight: 500; justify-content: flex-start' : 'justify-content: flex-start'"
            @click="enterFolder(null)"
          >根目录</a-button>
          <a-tree
            v-if="treeData.length"
            :data="treeData"
            size="small"
            default-expand-all
            :selected-keys="currentFolderId ? [currentFolderId] : []"
            @select="onTreeNodeSelect"
          />
        </div>
        <!-- 右：面包屑 + 文件表 -->
        <div style="flex: 1 1 auto; overflow-y: auto; min-width: 0">
          <a-breadcrumb style="margin-bottom: 10px">
            <a-breadcrumb-item>
              <a-link :style="currentFolderId === null ? 'color: var(--color-text-1)' : ''" @click="enterFolder(null)">根目录</a-link>
            </a-breadcrumb-item>
            <a-breadcrumb-item v-for="(seg, i) in breadcrumb" :key="i">
              <a-link :style="i === breadcrumb.length - 1 ? 'color: var(--color-text-1)' : ''" @click="enterFolder(segmentIds[i])">{{ seg }}</a-link>
            </a-breadcrumb-item>
          </a-breadcrumb>

          <a-table
            :data="currentEntries"
            :loading="filesLoading"
            :pagination="false"
            size="small"
            :bordered="{ cell: false }"
          >
            <template #columns>
              <a-table-column title="名称">
                <template #cell="{ record }">
                  <a-space>
                    <a-image
                      v-if="!record.__isDir && isImage(record.ext) && record.url"
                      :src="record.url"
                      :width="32"
                      :height="32"
                      fit="cover"
                      style="border-radius: 3px; vertical-align: middle"
                    />
                    <icon-folder v-else-if="record.__isDir" style="color: rgb(var(--warning-6)); font-size: 20px" />
                    <span
                      :style="record.__isDir ? 'cursor: pointer' : ''"
                      @click="record.__isDir ? enterFolder(record.id) : undefined"
                    >{{ record.__isDir ? record.name : (record.originalName || record.path) }}</span>
                  </a-space>
                </template>
              </a-table-column>
              <a-table-column title="大小" :width="100" align="center">
                <template #cell="{ record }">{{ record.__isDir ? '--' : formatSize(record.size) }}</template>
              </a-table-column>
              <a-table-column title="修改时间" :width="170">
                <template #cell="{ record }">{{ record.updatedAt ?? record.createdAt ?? '-' }}</template>
              </a-table-column>
              <a-table-column title="操作" :width="360">
                <template #cell="{ record }">
                  <a-space>
                    <template v-if="record.__isDir">
                      <a-button size="mini" type="text" @click="openMkdir(record)">新建子目录</a-button>
                      <a-button size="mini" type="text" @click="openRenameDir(record)">重命名</a-button>
                      <a-popconfirm content="递归删除目录与其下全部内容？" type="warning" @ok="deleteDir(record)">
                        <a-button size="mini" status="danger" type="text">删除</a-button>
                      </a-popconfirm>
                    </template>
                    <template v-else>
                      <a-button size="mini" type="text" @click="copyLink(record)">复制链接</a-button>
                      <a-button size="mini" type="text" @click="downloadFile(record)">下载</a-button>
                      <a-button size="mini" type="text" @click="openRenameFile(record)">重命名</a-button>
                      <a-dropdown @select="(v: any) => moveFileTo(record, Number(v))">
                        <a-button size="mini" type="text">移动到</a-button>
                        <template #content>
                          <a-doption :value="0">根目录</a-doption>
                          <a-doption v-for="d in moveTargets(record)" :key="d.id" :value="d.id">{{ d.name }}</a-doption>
                        </template>
                      </a-dropdown>
                      <a-popconfirm content="确认删除？将同时清理存储对象" type="warning" @ok="deleteFile(record)">
                        <a-button size="mini" status="danger" type="text">删除</a-button>
                      </a-popconfirm>
                    </template>
                  </a-space>
                </template>
              </a-table-column>
            </template>
          </a-table>
        </div>
      </div>
    </a-card>

    <!-- 新建/重命名目录弹窗 -->
    <a-modal
      v-model:visible="mkdirVisible"
      :title="mkdirForm.id ? '重命名目录' : '新建目录'"
      :width="420"
      :mask-closable="false"
      @before-ok="submitMkdir"
      :ok-loading="mkdirSaving"
    >
      <a-form :model="mkdirForm" :label-col-props="{ span: 5 }" :wrapper-col-props="{ span: 19 }">
        <a-form-item label="目录名" required>
          <a-input v-model="mkdirForm.name" placeholder="如：产品图" @press-enter="() => submitMkdir()" />
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- 文件重命名弹窗 -->
    <a-modal
      v-model:visible="renameVisible"
      title="重命名文件"
      :width="420"
      :mask-closable="false"
      @before-ok="submitRenameFile"
      :ok-loading="renameSaving"
    >
      <a-form :model="renameForm" :label-col-props="{ span: 5 }" :wrapper-col-props="{ span: 19 }">
        <a-form-item label="新名称" required>
          <a-input v-model="renameForm.name" @press-enter="() => submitRenameFile()" />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { computed, onMounted, reactive, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import { getToken } from '@/utils/auth';
  import field from '@/utils/field';

  // 统一 raw 请求：全局拦截器已解包信封（res 即 {code, data, message}），失败路径以失败信封落定
  async function api(method: any, url: string, data?: any): Promise<any> {
    try {
      return await axios.request({
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        url,
        method,
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        data,
      });
    } catch (e: any) {
      Message.error(e?.message ?? '请求失败');
      return { code: '-1', data: null, message: e?.message ?? '' };
    }
  }

  // ---------- 统计 ----------
  const stats = reactive({ total: 0, size: 0, monthNew: 0 });

  function formatSize(bytes: number | string): string {
    const b = Number(bytes);
    if (!b) return '--';
    if (b < 1024) return `${b} B`;
    if (b < 1024 * 1024) return `${(b / 1024).toFixed(1)} KB`;
    return `${(b / 1024 / 1024).toFixed(2)} MB`;
  }

  async function loadStats() {
    const res = await api('GET', '/attachment/stats');
    if (res.code === '0') Object.assign(stats, res.data ?? {});
  }

  // ---------- 渠道 ----------
  const channels = ref<any[]>([]);
  const currentChannelId = ref<number | undefined>(undefined);

  async function loadChannels() {
    const res: any = await api('GET', '/auto/storage-channel/all');
    if (res.code === '0') channels.value = res.data ?? [];
    if (currentChannelId.value === null) {
      const def = channels.value.find((c) => c.isDefault) ?? channels.value[0];
      currentChannelId.value = def?.id ?? null;
    }
  }

  function onChannelChange() {
    currentFolderId.value = null;
    refresh();
  }

  // ---------- 目录树 ----------
  const flatNodes = ref<any[]>([]);
  const treeData = ref<any[]>([]);
  const currentFolderId = ref<number | null>(null);

  // 平铺 nodes 组装嵌套树（根哨兵 id=0 不进树）
  function buildTree() {
    const mk = (parentId: number | null): any[] =>
      flatNodes.value
        .filter((n) => (n.parentId ?? null) === parentId)
        .map((n) => ({ key: n.id, title: n.name, id: n.id, name: n.name, parentId: n.parentId, children: mk(n.id) }));
    treeData.value = mk(0);
  }

  async function loadTree() {
    const res: any = await api('GET', `/storage/folders?channel_id=${currentChannelId.value}`);
    if (res.code === '0') {
      flatNodes.value = res.data?.nodes ?? [];
      buildTree();
    }
  }

  function findNode(nodes: any[], id: number): any {
    for (const n of nodes) {
      if (n.id === id) return n;
      const hit = findNode(n.children ?? [], id);
      if (hit) return hit;
    }
    return null;
  }

  // 面包屑：从根到当前目录
  const breadcrumb = computed(() => chainToRoot().map((n) => n.name));
  const segmentIds = computed(() => chainToRoot().map((n) => n.id));

  function chainToRoot(): any[] {
    if (currentFolderId.value === null) return [];
    const chain: any[] = [];
    let cur = findNode(treeData.value, currentFolderId.value);
    while (cur) {
      chain.unshift(cur);
      cur = cur.parentId ? findNode(treeData.value, cur.parentId) : null;
    }
    return chain;
  }

  function onTreeNodeSelect(keys: (string | number)[]) {
    const key = keys[0];
    if (key === undefined || Number(key) === 0) return;
    enterFolder(Number(key));
  }

  function enterFolder(id: number | null) {
    currentFolderId.value = id;
    loadFiles();
  }

  // ---------- 文件与子目录 ----------
  const filesLoading = ref(false);
  const allAttachments = ref<any[]>([]);

  // 当前目录视图：子目录行 + 附件行（合成）
  const currentEntries = computed(() => {
    const dirs = flatNodes.value
      .filter((n) => (n.parentId ?? null) === currentFolderId.value)
      .map((n) => ({ __isDir: true, id: n.id, name: n.name, updatedAt: n.updatedAt }));
    const files = allAttachments.value
      .filter((a) => (a.folderId ?? null) === currentFolderId.value)
      .map((a) => ({ ...a, __isDir: false }));
    return [...dirs, ...files];
  });

  async function loadFiles() {
    filesLoading.value = true;
    try {
      const res: any = await api('POST', '/auto/attachment/rows?page=1&page_size=1000', {});
      if (res.code === '0') allAttachments.value = res.data?.data ?? [];
    } finally {
      filesLoading.value = false;
    }
  }

  async function refresh() {
    await Promise.all([loadTree(), loadFiles(), loadStats()]);
  }

  // ---------- 上传 ----------
  const uploading = ref(false);

  async function doUpload(option: any): Promise<any> {
    // Arco customRequest 类型宽松化（发起请求后自行管理状态）
    uploading.value = true;
    try {
      const form = new FormData();
      form.append('file', option.fileItem.file);
      form.append('channel_id', String(currentChannelId.value ?? ''));
      if (currentFolderId.value !== null) form.append('folder_id', String(currentFolderId.value));
      await axios.post('/upload', form, {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        headers: {
          Authorization: `Bearer ${getToken() ?? ''}`,
          'Content-Type': 'multipart/form-data',
        },
      });
      Message.success(`已上传 ${option.fileItem.name}`);
      await Promise.all([loadFiles(), loadStats()]);
    } catch (e: any) {
      Message.error(e?.message ?? '上传失败');
    } finally {
      uploading.value = false;
    }
  }

  // ---------- 目录 ----------
  const mkdirVisible = ref(false);
  const mkdirSaving = ref(false);
  const mkdirForm = reactive({ id: 0, name: '', renameTarget: null as any });

  function openMkdir(_parent?: any) {
    mkdirForm.id = 0;
    mkdirForm.name = '';
    mkdirForm.renameTarget = null;
    mkdirVisible.value = true;
  }

  function openRenameDir(record: any) {
    mkdirForm.id = record.id;
    mkdirForm.name = record.name;
    mkdirForm.renameTarget = record;
    mkdirVisible.value = true;
  }

  async function submitMkdir(done?: (closed: boolean) => void) {
    const name = mkdirForm.name.trim();
    if (!name) {
      Message.error('请填写目录名');
      return;
    }
    mkdirSaving.value = true;
    try {
      let res: any;
      if (mkdirForm.id) {
        res = await api('PUT', `/storage/folders/${mkdirForm.id}`, { name });
      } else {
        res = await api('POST', '/storage/folders', {
          channel_id: currentChannelId.value,
          parent_id: currentFolderId.value,
          name,
        });
      }
      if (res.code === '0') {
        Message.success(mkdirForm.id ? '已重命名' : '目录已创建');
        mkdirVisible.value = false;
        await refresh();
        done?.(true);
      }
    } catch (e: any) {
      Message.error(e?.message ?? '操作失败');
    } finally {
      mkdirSaving.value = false;
    }
  }

  async function deleteDir(record: any) {
    const res = await api('DELETE', `/storage/folders/${record.id}?recursive=1`);
    if (res.code === '0') {
      Message.success('目录已删除');
      if (currentFolderId.value === record.id) currentFolderId.value = null;
      await refresh();
    }
  }

  // ---------- 文件操作 ----------
  const renameVisible = ref(false);
  const renameSaving = ref(false);
  const renameForm = reactive({ id: 0, name: '' });

  function openRenameFile(record: any) {
    renameForm.id = record.id;
    renameForm.name = record.originalName ?? '';
    renameVisible.value = true;
  }

  async function submitRenameFile(done?: (closed: boolean) => void) {
    const name = renameForm.name.trim();
    if (!name) {
      Message.error('请填写新名称');
      return;
    }
    renameSaving.value = true;
    try {
      // hump2underline 为原地转换无返回值（项目约定：先赋值再转换）
      const payload: any = { originalName: name };
      field.hump2underline(payload);
      const res = await api('PUT', `/auto/attachment/${renameForm.id}`, payload);
      if (res.code === '0') {
        Message.success('已重命名');
        renameVisible.value = false;
        done?.(true);
        loadFiles();
      }
    } catch (e: any) {
      Message.error(e?.message ?? '操作失败');
    } finally {
      renameSaving.value = false;
    }
  }

  // 移动目标：排除自身与自身子树（简化：排除自身所在目录项）
  function moveTargets(record: any) {
    const targets = [{ id: 0, name: '根目录' }];
    for (const n of flatNodes.value) {
      if (n.id !== (record.folderId ?? -1)) targets.push({ id: n.id, name: n.name });
    }
    return targets;
  }

  async function moveFileTo(record: any, folderId: number) {
    // hump2underline 为原地转换无返回值（项目约定：先赋值再转换）
    const payload: any = { folderId };
    field.hump2underline(payload);
    const res = await api('PUT', `/auto/attachment/${record.id}`, payload);
    if (res.code === '0') {
      Message.success('已移动');
      loadFiles();
    }
  }

  async function copyLink(record: any) {
    try {
      await navigator.clipboard.writeText(record.url ?? '');
      Message.success('链接已复制');
    } catch {
      Message.error('复制失败');
    }
  }

  async function downloadFile(record: any) {
    try {
      const res = await axios.get(`/storage/file?path=${encodeURIComponent(ltrim(record.path))}`, {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        responseType: 'blob',
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
      });
      const url = URL.createObjectURL(res.data as Blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = record.originalName || record.path;
      a.click();
      URL.revokeObjectURL(url);
    } catch (e: any) {
      Message.error(e?.message ?? '下载失败');
    }
  }

  function ltrim(s: string): string {
    return s.startsWith('/') ? s.slice(1) : s;
  }

  const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

  function isImage(ext: string): boolean {
    return IMAGE_EXTS.includes((ext || '').toLowerCase());
  }

  async function deleteFile(record: any) {
    const res = await api('DELETE', `/auto/attachment/${record.id}`);
    if (res.code === '0') {
      Message.success('已删除');
      allAttachments.value = allAttachments.value.filter((a) => a.id !== record.id);
      loadStats();
    }
  }

  onMounted(async () => {
    await loadChannels();
    await Promise.all([loadFiles(), loadStats()]);
  });
</script>
