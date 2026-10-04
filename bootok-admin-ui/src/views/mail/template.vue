<template>
  <a-card :bordered="false" title="邮件模板">
    <template #extra>
      <a-button type="primary" @click="openTemplate()" v-permission="'mail-template.create'">
        <template #icon><icon-plus /></template>
        新建模板
      </a-button>
    </template>

    <a-table :data="rows" :loading="loading" :pagination="false">
      <template #columns>
        <a-table-column title="ID" data-index="id" :width="70" />
        <a-table-column title="标识" data-index="code" :width="160" />
        <a-table-column title="名称" data-index="title" :width="160" />
        <a-table-column title="主题" data-index="subject" ellipsis tooltip />
        <a-table-column title="业务变量" :width="140">
          <template #cell="{ record }">
            <a-tag v-for="(v, i) in record.variables ?? []" :key="i" size="small" style="margin-right: 4px">${{ '{' }}{{ v.name }}{{ '}' }}</a-tag>
            <span v-if="!record.variables?.length" style="color: var(--color-text-3)">无</span>
          </template>
        </a-table-column>
        <a-table-column title="更新时间" data-index="updatedAt" :width="170" />
        <a-table-column title="操作" :width="280">
          <template #cell="{ record }">
            <a-space>
              <a-button size="mini" @click="previewTemplate(record)">预览</a-button>
              <a-button size="mini" type="outline" @click="openSendTest(record)">发送测试</a-button>
              <a-button size="mini" type="outline" @click="openTemplate(record)" v-permission="'mail-template.edit'">编辑</a-button>
              <a-popconfirm content="确认删除该模板？业务代码仍引用时发送将报错" type="warning" @ok="deleteTemplate(record.id)">
                <a-button size="mini" status="danger" v-permission="'mail-template.delete'">删除</a-button>
              </a-popconfirm>
            </a-space>
          </template>
        </a-table-column>
      </template>
    </a-table>

    <!-- 模板编辑弹窗 -->
    <a-modal
      v-model:visible="visible"
      :title="form.id ? '编辑邮件模板' : '新建邮件模板'"
      :width="760"
      :mask-closable="false"
      @before-ok="handleOk"
    >
      <a-form :model="form" class="form-template" label-align="right" auto-label-width>
        <a-form-item label="标识" required help="业务引用键，小写字母开头 a-z0-9_，如 verify_code；保存后勿再改动">
          <a-input v-model="form.code" :disabled="!!form.id" placeholder="verify_code" />
        </a-form-item>
        <a-form-item label="名称" required>
          <a-input v-model="form.title" placeholder="邮箱验证码" />
        </a-form-item>
        <a-form-item label="邮件主题" required help="支持变量 {$...}，全局变量 {$site_name}、{$year} 自动可用">
          <a-input v-model="form.subject" />
        </a-form-item>
        <a-form-item label="业务变量" help="声明供预览与插入参考；name 为变量名，label 为说明">
          <div class="var-list">
            <a-space v-for="(item, i) in form.variables" :key="i" class="var-row">
              <a-input v-model="item.name" placeholder="变量名，如 code" style="width: 200px" />
              <a-input v-model="item.label" placeholder="说明，如 验证码" style="width: 240px" />
              <a-button size="mini" status="danger" @click="form.variables.splice(i, 1)">删除</a-button>
            </a-space>
            <a-button size="mini" type="dashed" @click="form.variables.push({ name: '', label: '' })">添加变量</a-button>
          </div>
        </a-form-item>
        <a-form-item label="用途说明">
          <a-input v-model="form.remark" />
        </a-form-item>
        <a-form-item label="正文 HTML" required class="body-item">
          <a-textarea v-model="form.body" :auto-size="{ minRows: 14, maxRows: 24 }" class="body-editor" placeholder="完整 HTML，支持 {$var} 变量占位" />
        </a-form-item>
      </a-form>
    </a-modal>

    <!-- 预览弹窗 -->
    <a-modal v-model:visible="previewVisible" title="模板预览" :width="680" :footer="false">
      <div class="preview-meta">
        <span>主题：{{ previewSubject }}</span>
      </div>
      <iframe :srcdoc="previewHtml" class="preview-frame" sandbox="" />
    </a-modal>

    <!-- 发送测试弹窗 -->
    <a-modal v-model:visible="testVisible" title="发送测试邮件" :width="440" @before-ok="handleSendTest" :mask-closable="false">
      <a-form :model="{ testTo }">
        <a-form-item label="收件邮箱" required help="按模板示例变量渲染实发">
          <a-input v-model="testTo" placeholder="you@example.com" />
        </a-form-item>
      </a-form>
    </a-modal>
  </a-card>
</template>

<script lang="ts" setup>
  import { onMounted, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import BaseApi from '@/api/base';
  import field from '@/utils/field';
  import { getToken } from '@/utils/auth';

  const templateApi = new BaseApi('MailTemplate');

  const rows = ref<any[]>([]);
  const loading = ref(false);
  const visible = ref(false);
  const form = ref(emptyTemplateForm());

  function emptyTemplateForm() {
    return {
      id: 0,
      code: '',
      title: '',
      subject: '',
      body: '',
      remark: '',
      variables: [] as { name: string; label: string }[],
    };
  }

  async function loadRows() {
    loading.value = true;
    try {
      const res = await templateApi.getRows(1, {}, 100);
      rows.value = res.data?.data ?? [];
    } finally {
      loading.value = false;
    }
  }

  function openTemplate(record?: any) {
    if (record) {
      form.value = {
        id: record.id,
        code: record.code,
        title: record.title,
        subject: record.subject,
        body: record.body ?? '',
        remark: record.remark ?? '',
        variables: (record.variables ?? []).map((v: any) => ({ ...v })),
      };
    } else {
      form.value = emptyTemplateForm();
    }
    visible.value = true;
  }

  async function handleOk(done: (closed: boolean) => void) {
    const f = form.value;
    if (!f.code.trim() || !f.title.trim() || !f.subject.trim() || !f.body.trim()) {
      Message.error('请填写标识、名称、主题与正文');
      return;
    }
    try {
      const payload: any = {
        code: f.code,
        title: f.title,
        subject: f.subject,
        body: f.body,
        remark: f.remark,
        variables: f.variables,
      };
      field.hump2underline(payload);
      const res: any = await axios.request({
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        url: f.id ? `/auto/mail-template/${f.id}` : '/auto/mail-template',
        method: f.id ? 'PUT' : 'POST',
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        data: payload,
      });
      if (res.code === '0') {
        Message.success(f.id ? '已更新' : '已创建');
        done(true);
        loadRows();
      }
    } catch (e: any) {
      Message.error(e?.message ?? '保存失败');
    }
  }

  async function deleteTemplate(id: number) {
    const res = await templateApi.deleteItem(id);
    if (res.code === '0') {
      Message.success('已删除');
      loadRows();
    }
  }

  // 预览：后端示例变量渲染，iframe 隔离展示
  const previewVisible = ref(false);
  const previewSubject = ref('');
  const previewHtml = ref('');

  async function previewTemplate(record: any) {
    const res: any = await axios.post('/mail/template/preview', { id: record.id }, {
      baseURL: import.meta.env.VITE_API_BASE_URL as string,
      headers: { Authorization: `Bearer ${getToken() ?? ''}` },
    });
    if (res.code === '0') {
      previewSubject.value = res.data?.subject ?? '';
      previewHtml.value = res.data?.html ?? '';
      previewVisible.value = true;
    }
  }

  // 发送测试
  const testVisible = ref(false);
  const testTo = ref('');
  const testTemplateId = ref(0);

  function openSendTest(record: any) {
    testTemplateId.value = record.id;
    testTo.value = '';
    testVisible.value = true;
  }

  async function handleSendTest(done: (closed: boolean) => void) {
    if (!testTo.value.trim()) {
      Message.error('请填写测试收件邮箱');
      return;
    }
    try {
      const res: any = await axios.post(`/mail/template/${testTemplateId.value}/send-test`, { to: testTo.value }, {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        timeout: 30000,
      });
      if (res.code === '0') {
        Message.success(res.data?.message || '测试邮件已发送');
        done(true);
      }
    } catch (e: any) {
      Message.error(e?.message ?? '发送失败');
    }
  }

  onMounted(loadRows);
</script>

<style scoped>
  .var-list {
    width: 100%;
  }
  .var-row {
    margin-bottom: 8px;
  }
  .body-item :deep(.arco-textarea) {
    font-family: Consolas, Menlo, monospace;
    font-size: 12px;
    line-height: 1.6;
  }
  .preview-meta {
    margin-bottom: 8px;
    font-size: 13px;
    color: var(--color-text-2);
    word-break: break-all;
  }
  .preview-frame {
    width: 100%;
    height: 480px;
    border: 1px solid var(--color-border-2);
    border-radius: 4px;
    background: #fff;
  }
</style>
