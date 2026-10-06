<template>
  <div>
    <!-- 统计概览 -->
    <a-row :gutter="12">
      <a-col :span="6">
        <a-card :bordered="false" class="stat-card">
          <div class="stat-body">
            <div class="stat-icon" style="background: rgb(var(--blue-1)); color: rgb(var(--blue-6))">
              <icon-file style="font-size: 26px" />
            </div>
            <div>
              <div class="stat-value">{{ stats.total }}</div>
              <div class="stat-label">附件总数</div>
            </div>
          </div>
        </a-card>
      </a-col>
      <a-col :span="6">
        <a-card :bordered="false" class="stat-card">
          <div class="stat-body">
            <div class="stat-icon" style="background: rgb(var(--green-1)); color: rgb(var(--green-6))">
              <icon-storage style="font-size: 26px" />
            </div>
            <div>
              <div class="stat-value">{{ formatSize(stats.size) }}</div>
              <div class="stat-label">总大小</div>
            </div>
          </div>
        </a-card>
      </a-col>
      <a-col :span="6">
        <a-card :bordered="false" class="stat-card">
          <div class="stat-body">
            <div class="stat-icon" style="background: rgb(var(--orange-1)); color: rgb(var(--orange-6))">
              <icon-arrow-rise style="font-size: 26px" />
            </div>
            <div>
              <div class="stat-value">{{ stats.monthNew }}</div>
              <div class="stat-label">本月新增</div>
            </div>
          </div>
        </a-card>
      </a-col>
      <a-col :span="6">
        <a-card :bordered="false" class="stat-card">
          <div class="stat-body">
            <div class="stat-icon" style="background: rgb(var(--purple-1)); color: rgb(var(--purple-6))">
              <icon-cloud style="font-size: 26px" />
            </div>
            <div>
              <div class="stat-value">{{ currentChannelTitle }}</div>
              <div class="stat-label">当前渠道</div>
            </div>
          </div>
        </a-card>
      </a-col>
    </a-row>

    <!-- 文件管理器 -->
    <a-card :bordered="false" style="margin-top: 12px" :body-style="{ padding: '12px' }">
      <template #title>
        <a-space>
          <span>文件管理</span>
          <a-select
            v-model="currentChannelId"
            style="width: 210px"
            size="small"
            placeholder="存储渠道"
            @change="onChannelChange"
          >
            <a-option v-for="c in channels" :key="c.id" :value="c.id">
              {{ c.title }}
              <a-tag v-if="c.isDefault" color="green" size="small" style="margin-left: 4px">默认</a-tag>
            </a-option>
          </a-select>
        </a-space>
      </template>
      <template #extra>
        <a-space>
          <a-upload :custom-request="(doUpload as any)" :show-file-list="false" multiple>
            <a-button type="primary" size="small" :loading="uploading">
              <template #icon><icon-upload /></template>
              上传
            </a-button>
          </a-upload>
          <a-button size="small" @click="openMkdir(null)">
            <template #icon><icon-folder-add /></template>
            新建目录
          </a-button>
          <a-button size="small" @click="refresh">
            <template #icon><icon-refresh /></template>
            刷新
          </a-button>
        </a-space>
      </template>

      <div style="display: flex; gap: 12px; min-height: 480px">
        <!-- 左：目录树 -->
        <div style="width: 250px; flex: 0 0 250px; overflow-y: auto; border-right: 1px solid var(--color-border-2); padding-right: 8px">
          <div
            class="tree-root"
            :class="{ active: currentFolderId === null }"
            @click="enterFolder(null)"
          >
            <icon-home style="margin-right: 6px" />
            全部文件
          </div>
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
              <a-link :class="{ 'crumb-current': currentFolderId === null }" @click="enterFolder(null)">全部文件</a-link>
            </a-breadcrumb-item>
            <a-breadcrumb-item v-for="(seg, i) in breadcrumb" :key="i">
              <a-link :class="{ 'crumb-current': i === breadcrumb.length - 1 }" @click="enterFolder(segmentIds[i])">{{ seg }}</a-link>
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
                  <div class="file-name" @click="record.__isDir ? enterFolder(record.id) : previewFile(record)">
                    <a-image
                      v-if="!record.__isDir && isImage(record.ext) && record.url"
                      :src="fileUrl(record)"
                      :width="36"
                      :height="36"
                      fit="cover"
                      style="border-radius: 4px; flex: 0 0 36px"
                    />
                    <span v-else class="file-icon" :style="{ color: iconColor(record) }">
                      <icon-folder v-if="record.__isDir" style="font-size: 22px" />
                      <icon-image v-else-if="isImage(record.ext)" style="font-size: 20px" />
                      <icon-video-camera v-else-if="isVideo(record.ext)" style="font-size: 20px" />
                      <icon-music v-else-if="isAudio(record.ext)" style="font-size: 20px" />
                      <icon-archive v-else-if="isArchive(record.ext)" style="font-size: 20px" />
                      <icon-file v-else style="font-size: 20px" />
                    </span>
                    <span class="file-title">{{ record.__isDir ? record.name : (record.originalName || record.path) }}</span>
                  </div>
                </template>
              </a-table-column>
              <a-table-column title="大小" :width="90" align="center">
                <template #cell="{ record }">{{ record.__isDir ? '--' : formatSize(record.size) }}</template>
              </a-table-column>
              <a-table-column title="修改时间" :width="170">
                <template #cell="{ record }">{{ record.updatedAt ?? record.createdAt ?? '-' }}</template>
              </a-table-column>
              <a-table-column title="操作" :width="320">
                <template #cell="{ record }">
                  <a-space size="mini">
                    <template v-if="record.__isDir">
                      <a-button size="mini" type="text" @click="openMkdir(record)">新建子目录</a-button>
                      <a-button size="mini" type="text" @click="openMkdir(record)">重命名</a-button>
                      <a-popconfirm content="递归删除目录与其下全部内容？" type="warning" @ok="deleteDir(record)">
                        <a-button size="mini" status="danger" type="text">删除</a-button>
                      </a-popconfirm>
                    </template>
                    <template v-else>
                      <a-button v-if="isImage(record.ext)" size="mini" type="text" @click="previewFile(record)">预览</a-button>
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

    <!-- 图片预览 -->
    <a-image-preview v-model:visible="previewVisible" :src="previewSrc" />

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
  import field from '@/utils/field';
  import { getToken } from '@/utils/auth';

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
  const currentChannelTitle = computed(() => {
    const c = channels.value.find((x) => x.id === currentChannelId.value);
    return c ? c.title : '--';
  });

  async function loadChannels() {
    // 存储渠道未开放 getAll，走 rows 接口
    const res: any = await api('POST', '/auto/storage-channel/rows?page=1&page_size=100', {});
    if (res.code === '0') channels.value = res.data?.data ?? [];
    if (currentChannelId.value === undefined) {
      const def = channels.value.find((c) => c.isDefault) ?? channels.value[0];
      currentChannelId.value = def?.id ?? undefined;
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

  function buildTree() {
    const mk = (parentId: number | null): any[] =>
      flatNodes.value
        .filter((n) => (n.parentId ?? null) === parentId)
        .map((n) => ({ key: n.id, title: n.name, id: n.id, name: n.name, parentId: n.parentId, children: mk(n.id) }));
    treeData.value = [...mk(null), ...mk(0)];
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

  async function refresh() {
    await Promise.all([loadTree(), loadFiles(), loadStats()]);
  }

  // ---------- 文件与子目录 ----------
  const filesLoading = ref(false);
  const allAttachments = ref<any[]>([]);

  const currentEntries = computed(() => {
    const dirs = flatNodes.value
      .filter((n) => (n.parentId ?? null) === currentFolderId.value)
      .map((n) => ({ __isDir: true, id: n.id, name: n.name, updatedAt: n.updatedAt }));
    const files = allAttachments.value
      .filter((a) => (a.folderId ?? null) === currentFolderId.value)
      .map((a) => ({ ...a, __isDir: false }));
    return [...dirs, ...files];
  });

  const signedUrls = ref<Record<number, string>>({});

  // 私有读桶场景：图片/预览地址需带签名的临时 URL（公开读 CDN 地址本身可用，签名亦无害）
  async function loadSignedUrls() {
    const ids = allAttachments.value.filter((a) => isImage(a.ext)).map((a) => a.id).slice(0, 200);
    if (ids.length === 0) {
      signedUrls.value = {};
      return;
    }
    const res: any = await api('POST', '/attachment/signed-urls', { ids });
    if (res.code === '0') signedUrls.value = res.data?.urls ?? {};
  }

  async function loadFiles() {
    filesLoading.value = true;
    try {
      const res: any = await api('POST', '/auto/attachment/rows?page=1&page_size=1000', { channel_id: currentChannelId.value });
      if (res.code === '0') {
        allAttachments.value = res.data?.data ?? [];
        await loadSignedUrls();
      }
    } finally {
      filesLoading.value = false;
    }
  }

  // ---------- 类型 ----------
  // 附件访问地址：私有读桶用签名临时 URL，公开读直链/CDN 地址直接用
  function fileUrl(record: any): string {
    return signedUrls.value[record.id] || record.url || "";
  }

  const IMAGE_EXTS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
  const VIDEO_EXTS = ['mp4', 'avi', 'mov', 'wmv', 'flv'];
  const AUDIO_EXTS = ['mp3', 'wav', 'flac', 'ogg'];
  const ARCHIVE_EXTS = ['zip', 'rar', '7z', 'gz', 'tar'];

  function isImage(ext: string): boolean {
    return IMAGE_EXTS.includes((ext || '').toLowerCase());
  }
  function isVideo(ext: string): boolean {
    return VIDEO_EXTS.includes((ext || '').toLowerCase());
  }
  function isAudio(ext: string): boolean {
    return AUDIO_EXTS.includes((ext || '').toLowerCase());
  }
  function isArchive(ext: string): boolean {
    return ARCHIVE_EXTS.includes((ext || '').toLowerCase());
  }
  function iconColor(record: any): string {
    if (record.__isDir) return 'rgb(var(--warning-6))';
    const e = (record.ext || '').toLowerCase();
    if (isImage(e)) return 'rgb(var(--green-6))';
    if (isVideo(e)) return 'rgb(var(--purple-6))';
    if (isAudio(e)) return 'rgb(var(--pink-6))';
    if (isArchive(e)) return 'rgb(var(--orange-6))';
    return 'rgb(var(--blue-6))';
  }

  // ---------- 图片预览 ----------
  const previewVisible = ref(false);
  const previewSrc = ref('');

  function previewFile(record: any) {
    const u = fileUrl(record);
    if (!u) return;
    previewSrc.value = u;
    previewVisible.value = true;
  }

  // ---------- 上传 ----------
  const uploading = ref(false);

  async function doUpload(option: any): Promise<any> {
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
      await refresh();
    } catch (e: any) {
      Message.error(e?.message ?? '上传失败');
    } finally {
      uploading.value = false;
    }
    return option;
  }

  // ---------- 目录 ----------
  const mkdirVisible = ref(false);
  const mkdirSaving = ref(false);
  const mkdirForm = reactive({ id: 0, name: '' });

  function openMkdir(record?: any) {
    mkdirForm.id = record ? record.id : 0;
    mkdirForm.name = record ? record.name : '';
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

  function downloadFile(record: any) {
    // 附件 URL 即公网/CDN 直链，新窗口打开由浏览器处理下载
    const u = fileUrl(record); if (u) window.open(u);
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
    await Promise.all([loadTree(), loadFiles(), loadStats()]);
  });
</script>

<style scoped>
  .stat-card :deep(.arco-card-body) {
    padding: 16px 20px;
  }
  .stat-body {
    display: flex;
    align-items: center;
    gap: 14px;
  }
  .stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .stat-value {
    font-size: 22px;
    font-weight: 600;
    line-height: 1.3;
  }
  .stat-label {
    font-size: 12px;
    color: var(--color-text-3);
  }

  .tree-root {
    display: flex;
    align-items: center;
    padding: 5px 8px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    color: var(--color-text-2);
    margin-bottom: 4px;
  }
  .tree-root:hover {
    background: var(--color-fill-2);
  }
  .tree-root.active {
    background: var(--color-fill-2);
    color: var(--color-text-1);
    font-weight: 500;
  }
  .crumb-current {
    color: var(--color-text-1);
  }

  .file-name {
    display: flex;
    align-items: center;
    gap: 10px;
    cursor: default;
  }
  .file-icon {
    display: inline-flex;
  }
  .file-title {
    word-break: break-all;
  }
  .row-dir .file-title {
    font-weight: 500;
  }
</style>
