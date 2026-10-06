<template>
  <div>
    <a-card :bordered="false" :title="`${modelText}列表`">
      <template #extra>
        <slot name="extra"></slot>
      </template>
      <slot name="search"></slot>

      <a-space direction="vertical" fill>
        <a-row justify="space-between">
          <a-col :span="12">
            <a-space>
              <a-radio-group
                v-if="trash"
                v-model="mode"
                type="button"
                size="small"
                @change="onModeChange"
              >
                <a-radio value="normal">正常数据</a-radio>
                <a-radio value="trash">回收站</a-radio>
              </a-radio-group>
              <template v-if="mode === 'normal'">
                <a-button
                  type="primary"
                  @click="editItem()"
                  v-if="addEnable"
                  v-permission="modelName + '.create'"
                >
                  <template #icon>
                    <icon-plus />
                  </template>
                  新建
                </a-button>
                <a-button
                  @click="exportItems"
                  :loading="exporting"
                  v-permission="modelName + '.export'"
                >
                  导出
                </a-button>
                <a-button
                  @click="openImport"
                  v-permission="modelName + '.import'"
                >
                  导入
                </a-button>
                <a-popconfirm
                  v-if="batch && deleteEnable && selectedRowKeys.length > 0"
                  :content="`确认删除选中的 ${selectedRowKeys.length} 条数据？`"
                  type="warning"
                  @ok="batchDelete"
                >
                  <a-button status="danger" v-permission="modelName + '.delete'">
                    删除（{{ selectedRowKeys.length }}）
                  </a-button>
                </a-popconfirm>
              </template>
            </a-space>
          </a-col>
          <a-col :span="12" style="text-align: right">
            <slot name="right"></slot>
          </a-col>
        </a-row>

        <a-table
          :bordered="false"
          :columns="tableColumns"
          :data="tableData"
          :pagination="pagination"
          :loading="loading"
          :row-selection="rowSelection"
          @selection-change="onSelectionChange"
          @page-change="changePage"
          @page-size-change="changePageSize"
        >
          <template #optional="{ record }">
            <a-space>
              <slot name="action" :record="record"></slot>
              <template v-if="mode === 'normal'">
                <a-button
                  type="text"
                  status="success"
                  @click="editItem(record[keyName])"
                  v-if="editEnable"
                  v-permission="modelName + '.edit'"
                >
                  编辑
                </a-button>
                <a-button
                  type="text"
                  status="danger"
                  @click="askDelete(record[keyName])"
                  v-if="deleteEnable"
                  v-permission="modelName + '.delete'"
                >
                  删除
                </a-button>
              </template>
              <template v-else>
                <a-button
                  type="text"
                  status="success"
                  @click="restoreItem(record[keyName])"
                  v-permission="modelName + '.restore'"
                >
                  恢复
                </a-button>
                <a-popconfirm
                  content="彻底删除后不可恢复，确认？"
                  @ok="forceDeleteItem(record[keyName])"
                  type="warning"
                  v-permission="modelName + '.delete'"
                >
                  <a-button type="text" status="danger">彻底删除</a-button>
                </a-popconfirm>
              </template>
            </a-space>
          </template>
          <!-- 透传页面传入的自定义列插槽（如 #cover/#status） -->
          <template
            v-for="(_, name) in tableSlots"
            :key="name"
            #[name]="slotProps"
          >
            <slot :name="name" v-bind="slotProps ?? {}"></slot>
          </template>
        </a-table>
      </a-space>
    </a-card>

    <a-modal
      v-model:visible="visible"
      :title="(id ? '编辑' : '新建') + modelText"
      @ok="saveItem"
      :width="modalWidth"
      :top="'40px'"
      @cancel="modalCancel"
    >
      <a-form ref="myForm" :model="formData" label-align="right" auto-label-width>
        <!-- 字段栅格：字段类用 Field::col() 声明 24 栅格跨度（默认 24 通栏，两列布局用 12） -->
        <a-row :gutter="16">
        <a-col v-for="item in formItems" :key="item.field" :span="item.col ?? 24">
        <a-form-item
          :field="item.field"
          :label="item.label"
          :extra="item.help"
          :rules="
            item.required
              ? [{ required: true, message: `${item.label}不能为空` }]
              : undefined
          "
        >
          <template v-if="item.type === 'select'">
            <a-select
              v-model="formData[item.field]"
              :placeholder="`请选择${item.label}`"
            >
              <a-option v-for="di in item.data" :key="di.value" :value="di.value">
                {{ di.label }}
              </a-option>
            </a-select>
          </template>
          <template v-if="item.type === 'radio'">
            <a-radio-group v-model="formData[item.field]">
              <a-radio v-for="di in item.data" :key="di.value" :value="di.value">
                {{ di.label }}
              </a-radio>
            </a-radio-group>
          </template>
          <template v-if="item.type === 'text'">
            <a-input
              v-model="formData[item.field]"
              :placeholder="`请输入${item.label}`"
              :max-length="item.max"
            >
              <template v-if="item?.prepend" #prepend>{{ item.prepend }}</template>
              <template v-if="item?.append" #append>{{ item.append }}</template>
            </a-input>
          </template>
          <template v-if="item.type === 'password'">
            <a-input-password
              v-model="formData[item.field]"
              :placeholder="id ? '留空则不修改' : `请输入${item.label}`"
            />
          </template>
          <template v-if="item.type === 'textarea'">
            <a-textarea v-model="formData[item.field]"></a-textarea>
          </template>
          <template v-if="item.type === 'number'">
            <a-input-number
              v-model="formData[item.field]"
              :min="item.min"
              :max="item.max"
              :step="item.step"
            >
              <template v-if="item?.prepend" #prepend>{{ item.prepend }}</template>
              <template v-if="item?.append" #append>{{ item.append }}</template>
            </a-input-number>
          </template>
          <template v-if="item.type === 'date' || item.type === 'date-time'">
            <a-date-picker
              v-model="formData[item.field]"
              :show-time="item.type === 'date-time'"
              :placeholder="`请选择${item.label}`"
              style="width: 100%"
            />
          </template>
          <template v-if="item.type === 'file'">
            <a-upload
              :action="uploadAction"
              :headers="uploadHeaders"
              list-type="picture-card"
              image-preview
              :file-list="buildFileList(item.field, formData[item.field])"
              :data="{ remark: item.field }"
              @success="uploadSuccess"
              :on-before-remove="deleteFile"
            >
            </a-upload>
          </template>
          <template v-if="item.type === 'rich-text'">
            <editor
              :upload-server="editorUploadAction"
              v-model:content="formData[item.field]"
            ></editor>
          </template>
          <template v-if="item.type === 'time'">
            <a-time-picker
              type="time"
              v-model="formData[item.field]"
              format="HH:mm"
            />
          </template>
          <template v-if="item.type === 'checkbox'">
            <a-checkbox-group v-model="formData[item.field]">
              <a-checkbox v-for="di in item.data" :key="di.value" :value="di.value">
                {{ di.label }}
              </a-checkbox>
            </a-checkbox-group>
          </template>
          <template v-if="item.type === 'area-picker'">
            <a-cascader
              :options="item.data"
              :field-names="{ value: 'label', label: 'label' }"
              v-model:model-value="formData[item.field]"
              :path-mode="true"
              allow-clear
            />
          </template>
        </a-form-item>
        </a-col>
        </a-row>
      </a-form>
    </a-modal>

    <!-- 高危删除二次确认（dangerConfirm 启用：输入「删除」方可提交） -->
    <a-modal
      v-model:visible="deleteConfirmVisible"
      title="删除确认"
      :width="420"
      :ok-button-props="{ disabled: confirmText !== '删除' }"
      :ok-loading="deleting"
      ok-status="danger"
      ok-text="确认删除"
      @ok="confirmDelete"
      @cancel="deleteConfirmVisible = false"
      :unmount-on-close="true"
    >
      <a-alert type="warning" style="margin-bottom: 12px">
        该操作不可恢复。请输入「删除」以确认执行。
      </a-alert>
      <a-input v-model="confirmText" placeholder="删除" allow-clear />
    </a-modal>

    <!-- Excel 导入：模板下载 + 上传导入 + 行级错误报告 -->
    <a-modal
      v-model:visible="importVisible"
      title="导入数据"
      :width="600"
      :footer="false"
      :unmount-on-close="true"
    >
      <a-space direction="vertical" fill style="width: 100%">
        <a-alert type="info">
          先下载模板按格式填写（首行表头不可改），上传后逐行导入；失败行不影响其他行，完成后返回逐行错误报告。
        </a-alert>
        <a-space>
          <a-button @click="downloadTemplate" :loading="templateLoading">
            下载模板
          </a-button>
          <a-upload
            :custom-request="uploadImport"
            :show-file-list="false"
            accept=".xlsx"
          >
            <template #upload-button>
              <a-button type="primary" :loading="importing">
                选择文件导入
              </a-button>
            </template>
          </a-upload>
        </a-space>
        <template v-if="importResult">
          <a-alert :type="importResult.fail > 0 ? 'warning' : 'success'">
            共 {{ importResult.total }} 行：成功 {{ importResult.success }} 条，失败
            {{ importResult.fail }} 条
          </a-alert>
          <a-table
            v-if="importResult.errors?.length"
            :data="importResult.errors"
            :pagination="false"
            size="small"
            :bordered="false"
            :scroll="{ y: 260 }"
          >
            <a-table-column title="Excel 行号" :width="100">
              <template #cell="{ record }">{{ record.row }}</template>
            </a-table-column>
            <a-table-column title="失败原因">
              <template #cell="{ record }">{{ record.error }}</template>
            </a-table-column>
          </a-table>
        </template>
      </a-space>
    </a-modal>
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, type PropType, ref, useSlots } from 'vue';
  import axios from 'axios';
  import { useRoute, useRouter } from 'vue-router';
  import {
    type FileItem,
    Message,
    type PaginationProps,
    type TableColumnData,
    type TableData,
  } from '@arco-design/web-vue';
  import BaseApi from '@/api/base';
  import { getToken } from '@/utils/auth';
  import Editor from '@/components/editor/editor.vue';
  import type FormItem from '@/types/form-item';

  const props = defineProps({
    actionEnable: {
      type: Boolean,
      default: true,
    },
    addEnable: {
      type: Boolean,
      default: true,
    },
    editEnable: {
      type: Boolean,
      default: true,
    },
    deleteEnable: {
      type: Boolean,
      default: true,
    },
    modelName: {
      type: String,
      required: true,
    },
    modelObject: {
      type: Object,
    },
    modelText: {
      type: String,
      default: '',
    },
    keyName: {
      type: String,
      default: 'id',
    },
    columns: {
      type: Array as PropType<TableColumnData[]>,
      required: true,
    },
    filter: {
      type: Object as PropType<{ [key: string]: any }>,
      default: () => ({}),
    },
    modalWidth: {
      type: String,
      default: '700px',
    },
    showUpdateTime: {
      type: Boolean,
      default: true,
    },
    showCreateTime: {
      type: Boolean,
      default: true,
    },
    createTimeName: {
      type: String,
      default: '创建时间',
    },
    showId: {
      type: Boolean,
      default: true,
    },
    // 批量操作：勾选列 + 批量删除（仅在 deleteEnable 时生效）
    batch: {
      type: Boolean,
      default: true,
    },
    // 回收站：启用后列表顶部出现「正常数据/回收站」切换（模型需启用软删除）
    trash: {
      type: Boolean,
      default: false,
    },
    // 高危删除二次确认：删除需输入「删除」
    dangerConfirm: {
      type: Boolean,
      default: false,
    },
  });

  let id: string | number | null = null;

  const route = useRoute();
  const router = useRouter();

  const slots = useSlots();
  // 透传给 a-table 的自定义列插槽（排除crud自身消费的插槽）
  const tableSlots = computed(() => {
    const reserved = ['search', 'extra', 'right', 'action'];
    return Object.fromEntries(
      Object.entries(slots).filter(([name]) => !reserved.includes(name))
    );
  });

  // 批量勾选：仅在 batch 且 deleteEnable 时启用勾选列
  const rowSelection = computed(() => {
    if (!props.batch || !props.deleteEnable) {
      return undefined;
    }
    return { type: 'checkbox' as const, showCheckedAll: true, width: 40 };
  });

  function onSelectionChange(rowKeys: (string | number)[]) {
    selectedRowKeys.value = rowKeys;
  }

  // Excel 导出：复用列表 filter 条件，blob 下载
  async function exportItems() {
    exporting.value = true;
    try {
      const kebab = kebabName(props.modelName);
      const res = await axios.post(`/auto/${kebab}/export`, props.filter, {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        responseType: 'blob',
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
      });
      // 后端出错时返回 JSON 信封而非文件流
      const contentType = String(res.headers['content-type'] ?? '');
      if (contentType.includes('application/json')) {
        const text = await (res.data as Blob).text();
        const json = JSON.parse(text);
        Message.error(json.message || '导出失败');
        return;
      }
      const url = URL.createObjectURL(res.data as Blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `${kebab}_${new Date().toISOString().slice(0, 10)}.xlsx`;
      a.click();
      URL.revokeObjectURL(url);
      Message.success('导出成功');
    } catch (e) {
      Message.error('导出失败');
    } finally {
      exporting.value = false;
    }
  }

  // ===== Excel 导入：模板下载 + 上传 + 行级错误报告 =====
  const importVisible = ref(false);
  const templateLoading = ref(false);
  const importing = ref(false);
  const importResult = ref<ImportResult | null>(null);

  interface ImportResult {
    total: number;
    success: number;
    fail: number;
    errors: { row: number; error: string }[];
  }

  const kebabName = (name: string) =>
    name.replace(/([A-Z])/g, '-$1').toLowerCase();

  function openImport() {
    importResult.value = null;
    importVisible.value = true;
  }

  // 模板下载：blob 直下，后端出错时返回 JSON 信封
  async function downloadTemplate() {
    templateLoading.value = true;
    try {
      const kebab = kebabName(props.modelName);
      const res = await axios.get(`/auto/${kebab}/import-template`, {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        responseType: 'blob',
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
      });
      const contentType = String(res.headers['content-type'] ?? '');
      if (contentType.includes('application/json')) {
        const text = await (res.data as Blob).text();
        Message.error(JSON.parse(text).message || '模板下载失败');
        return;
      }
      const url = URL.createObjectURL(res.data as Blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `${kebab}_导入模板.xlsx`;
      a.click();
      URL.revokeObjectURL(url);
    } catch (e) {
      Message.error('模板下载失败');
    } finally {
      templateLoading.value = false;
    }
  }

  // 逐行导入可能超全局 30s 兜底，单独放宽到 5 分钟；错误信封由全局拦截器统一提示
  function uploadImport(option: any) {
    const fd = new FormData();
    fd.append('file', option.fileItem.file);
    importing.value = true;
    importResult.value = null;
    axios
      .post(`/auto/${kebabName(props.modelName)}/import`, fd, {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        timeout: 300000,
      })
      .then((body: any) => {
        importResult.value = body.data;
        Message.success(body.message || '导入完成');
        getItems();
        option.onSuccess(body);
      })
      .catch(() => {
        option.onError(new Error('import failed'));
      })
      .finally(() => {
        importing.value = false;
      });
    return { abort() {} };
  }

  function batchDelete() {
    api.batchDelete(selectedRowKeys.value).then((res: any) => {
      if (res.code === '0') {
        Message.success(res.message || '删除成功');
        selectedRowKeys.value = [];
        getItems();
      } else {
        Message.error(res.message);
      }
    });
  }

  const api = props.modelObject ?? new BaseApi(props.modelName);
  const uploadAction = `${import.meta.env.VITE_API_BASE_URL}/upload`;
  const editorUploadAction = `${import.meta.env.VITE_API_BASE_URL}/upload/editor`;
  const uploadHeaders = { Authorization: `Bearer ${getToken() ?? ''}` };

  const formItems = ref<FormItem[]>([]);
  const tableData = ref<TableData[]>([]);
  const tableColumns = ref<TableColumnData[]>([]);
  const formData = ref<{ [key: string]: any }>({});
  const visible = ref(false);
  const loading = ref(false);
  const exporting = ref(false);
  const selectedRowKeys = ref<(string | number)[]>([]);
  const mode = ref<'normal' | 'trash'>('normal');
  const deleteConfirmVisible = ref(false);
  const confirmText = ref('');
  const deleting = ref(false);
  let pendingDeleteId: string | number | null = null;
  const pagination = ref<PaginationProps>({});
  const currentPage = ref(1);
  const pageSize = ref(15);

  const emit = defineEmits<{
    (e: 'save-success'): void;
    (e: 'edit', id: Function): void;
  }>();

  defineExpose({
    getItems,
    editItem,
    setTableData,
  });

  onMounted(() => {
    buildTableColumns();
    // 从 URL query 恢复页码，刷新后停留在当前页
    const qp = parseInt(String(route.query.page ?? '1'), 10);
    getItems(Number.isFinite(qp) && qp > 0 ? qp : 1);
  });

  function buildFileList(name: string, url: string): FileItem[] {
    if (url === '' || url === undefined) {
      return [];
    }
    return [
      {
        uid: id as string,
        url,
        name,
      },
    ];
  }

  async function deleteFile(fileItem: FileItem) {
    // 新建时记录尚不存在，仅清除表单值即可（文件未归属任何记录）
    const fieldName = fileItem?.name as string;
    if (!id) {
      formData.value[fieldName] = '';
      return true;
    }
    const res = await api.deleteAttachment(id, fieldName);
    if (res.code === '0') {
      Message.success('删除成功');
      return true;
    }
    Message.error('删除失败');
    return false;
  }

  function uploadSuccess(fileItem: FileItem) {
    const res = (fileItem as any).response;
    if (res?.code === '0') {
      // 把组件生成的文件名替换为字段名，移除时才能定位表单字段
      fileItem.name = res.data.remark;
      formData.value[res.data.remark] = res.data.file_url;
    }
  }

  function buildTableColumns() {
    if (props.showId) {
      tableColumns.value.push({
        title: '#',
        dataIndex: props.keyName,
        fixed: 'left',
        width: 80,
      });
    }
    props.columns.forEach((item) => {
      tableColumns.value.push(item);
    });
    if (props.showUpdateTime) {
      tableColumns.value.push({
        title: '更新时间',
        dataIndex: 'updatedAt',
        width: 180,
        align: 'center',
      });
    }
    if (props.showCreateTime) {
      tableColumns.value.push({
        title: props.createTimeName,
        dataIndex: 'createdAt',
        align: 'center',
        width: 180,
      });
    }
    if (props.actionEnable) {
      tableColumns.value.push({
        title: '操作',
        slotName: 'optional',
        fixed: 'right',
        width: 180,
        align: 'right',
      });
    }
  }

  function changePage(val: number) {
    currentPage.value = val;
    syncPageQuery();
    getItems();
  }

  function changePageSize(val: number) {
    pageSize.value = val;
    currentPage.value = 1;
    syncPageQuery();
    getItems();
  }

  // 页码同步到 URL query，刷新后可恢复（page-layout 的 key 只按 path，
  // 此处 router.replace 不会引发页面重挂载）
  function syncPageQuery() {
    router.replace({ query: { ...route.query, page: String(currentPage.value) } });
  }

  function setTableData(
    data: { record: TableData; column: TableColumnData; rowIndex: number },
    value: number | boolean | string
  ) {
    const update: { [key: string]: number | string | boolean } = {};
    const field: string = data.column.dataIndex as string;
    update[field] = value;
    api.updateItem(data.record[props.keyName], update).then((res: any) => {
      if (res.code === '0') {
        tableData.value[data.rowIndex][field] = value;
        Message.success('设置成功');
      } else {
        Message.error('设置失败');
      }
    });
  }

  function getItems(page: number | null = null) {
    if (page !== null) {
      currentPage.value = page;
    }
    loading.value = true;
    const request =
      mode.value === 'trash'
        ? api.getTrashRows(currentPage.value, props.filter, pageSize.value)
        : api.getRows(currentPage.value, props.filter, pageSize.value);
    request.then((res: any) => {
      loading.value = false;
      tableData.value = res.data.data;
      pagination.value.total = res.data.total;
      pagination.value.pageSize = res.data.perPage;
      pagination.value.current = currentPage.value;
      pagination.value.showTotal = true;
      pagination.value.showPageSize = true;
      pagination.value.pageSizeOptions = [15, 30, 50, 100];
    });
  }

  function onModeChange() {
    selectedRowKeys.value = [];
    getItems(1);
  }

  // 高危删除入口：dangerConfirm 时弹输入确认，否则直接执行
  function askDelete(itemId: number | string) {
    if (props.dangerConfirm) {
      pendingDeleteId = itemId;
      confirmText.value = '';
      deleteConfirmVisible.value = true;
      return;
    }
    deleteItem(itemId);
  }

  function confirmDelete() {
    if (pendingDeleteId === null) return;
    deleting.value = true;
    api
      .deleteItem(pendingDeleteId)
      .then((res: any) => {
        deleting.value = false;
        if (res.code === '0') {
          Message.success('删除成功');
          deleteConfirmVisible.value = false;
          getItems();
        } else {
          Message.error(res.message);
        }
      })
      .catch(() => {
        deleting.value = false;
      });
  }

  function restoreItem(itemId: number | string) {
    api.restoreItem(itemId).then((res: any) => {
      if (res.code === '0') {
        Message.success('恢复成功');
        getItems();
      } else {
        Message.error(res.message);
      }
    });
  }

  function forceDeleteItem(itemId: number | string) {
    api.forceDeleteItem(itemId).then((res: any) => {
      if (res.code === '0') {
        Message.success('已彻底删除');
        getItems();
      } else {
        Message.error(res.message);
      }
    });
  }

  async function editItem(_id: number | string = 0) {
    let prevent = false;
    emit('edit', (v: boolean) => {
      prevent = v;
      return _id;
    });

    if (prevent) return;

    id = _id;
    if (id) {
      const item = await api.getItem(id);
      formData.value = item.data ?? {};
    } else {
      formData.value = {};
    }
    const formRes = await api.getForm();
    // 新建时应用字段默认值
    if (!id) {
      formRes.data.forEach((item: FormItem) => {
        if (item.default !== undefined && formData.value[item.field] === undefined) {
          formData.value[item.field] = item.default;
        }
      });
    }
    formItems.value = formRes.data;
    visible.value = true;
  }

  function clearFormData() {
    const data: { [key: string]: any } = {};
    formItems.value.map((item) => {
      data[item.field] = '';
      return item;
    });
    formData.value = data;
  }

  async function saveItem() {
    delete formData.value.createdAt;
    delete formData.value.updatedAt;
    const res = id
      ? await api.updateItem(id, formData.value)
      : await api.createItem(formData.value);
    if (res.code === '0') {
      Message.success('保存成功');
      emit('save-success');
      clearFormData();
      formItems.value = [];
    } else {
      visible.value = true;
      Message.error(res.message);
    }
    getItems();
  }

  function deleteItem(itemId: number | string) {
    api.deleteItem(itemId).then((res: any) => {
      if (res.code === '0') {
        Message.success('删除成功');
        getItems();
      } else {
        Message.error(res.message);
      }
    });
  }

  function modalCancel() {
    formItems.value = [];
  }
</script>
