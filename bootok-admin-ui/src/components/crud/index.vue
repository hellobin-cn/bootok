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
          @page-change="changePage"
          @page-size-change="changePageSize"
        >
          <template #optional="{ record }">
            <a-space>
              <slot name="action" :record="record"></slot>
              <a-button
                type="text"
                status="success"
                @click="editItem(record[keyName])"
                v-if="editEnable"
                v-permission="modelName + '.edit'"
              >
                编辑
              </a-button>
              <a-popconfirm
                content="确认删除?"
                @ok="deleteItem(record[keyName])"
                type="warning"
                v-if="deleteEnable"
                v-permission="modelName + '.delete'"
              >
                <a-button type="text" status="danger">删除</a-button>
              </a-popconfirm>
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
      <a-form ref="myForm" :model="formData">
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
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, type PropType, ref, useSlots } from 'vue';
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
    api
      .getRows(currentPage.value, props.filter, pageSize.value)
      .then((res: any) => {
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
