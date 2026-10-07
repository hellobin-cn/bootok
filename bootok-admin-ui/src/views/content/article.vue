<template>
  <crud
    ref="crudRef"
    model-name="article"
    model-text="文章"
    key-name="articleId"
    :columns="columns"
    :filter="filter"
    modal-width="1000px"
    trash
    :danger-confirm="true"
  >
    <template #search>
      <a-card :bordered="false" style="margin-bottom: 12px">
        <a-space>
          <a-input-search
            v-model="query.title"
            placeholder="搜索标题"
            style="width: 240px"
            allow-clear
            @search="() => crudRef.getItems(1)"
            @clear="() => crudRef.getItems(1)"
          />
          <a-select
            v-model="query.status"
            placeholder="状态"
            style="width: 140px"
            allow-clear
            @change="() => crudRef.getItems(1)"
            @clear="() => crudRef.getItems(1)"
          >
            <a-option :value="1">上架</a-option>
            <a-option :value="0">下架</a-option>
          </a-select>
        </a-space>
      </a-card>
    </template>
    <template #cover="{ record }">
      <a-image
        v-if="record.cover"
        :src="record.cover"
        width="60"
        height="40"
        fit="cover"
      />
      <span v-else>--</span>
    </template>
    <template #status="{ record }">
      <a-tag v-if="record.status === 1" color="green">上架</a-tag>
      <a-tag v-else color="red">下架</a-tag>
    </template>
    <template #action="{ record }">
      <a-button
        type="text"
        size="small"
        status="warning"
        v-permission="'article.wechat_publish'"
        @click="openWechatPublish(record)"
      >
        公众号
      </a-button>
    </template>
  </crud>

  <!-- 发公众号：摘要 + 自动发布选项 -->
  <a-modal
    v-model:visible="publishVisible"
    title="发布到公众号"
    :width="480"
    :ok-text="publishForm.auto_publish ? '发布并推送粉丝' : '保存为草稿'"
    :ok-loading="publishing"
    :ok-button-props="{ status: publishForm.auto_publish ? ('danger' as const) : ('normal' as const) }"
    @ok="confirmPublish"
    @cancel="publishVisible = false"
  >
    <a-form :model="publishForm" label-align="right" auto-label-width>
      <a-form-item label="文章">
        <span>{{ publishRecord?.title }}</span>
      </a-form-item>
      <a-form-item label="摘要" help="留空自动截取正文前 54 字（公众号列表页展示）">
        <a-textarea v-model="publishForm.digest" :auto-size="{ minRows: 2, maxRows: 4 }" :max-length="120" />
      </a-form-item>
      <a-form-item label="直接发布">
        <a-switch v-model="publishForm.auto_publish" />
        <span class="publish-hint">
          关闭 = 仅保存到公众号草稿箱（推荐，可先预览）；开启 = 直接推送给全部粉丝
        </span>
      </a-form-item>
    </a-form>
  </a-modal>
</template>

<script lang="ts" setup>
  import { computed, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import crud from '@/components/crud/index.vue';
  import type { TableColumnData } from '@arco-design/web-vue';

  const crudRef = ref();
  const query = ref({
    title: '',
    status: undefined as number | undefined,
  });

  const filter = computed(() => ({
    titleLike: query.value.title,
    status: query.value.status,
  }));

  const columns = ref<TableColumnData[]>([
    { title: '标题', dataIndex: 'title', ellipsis: true },
    { title: '封面', slotName: 'cover', width: 90, align: 'center' },
    { title: '状态', slotName: 'status', width: 90, align: 'center' },
    { title: '排序', dataIndex: 'sort', width: 90, align: 'center' },
  ]);

  // ===== 发公众号 =====
  const publishVisible = ref(false);
  const publishing = ref(false);
  const publishRecord = ref<any>(null);
  const publishForm = ref({ digest: '', auto_publish: false });

  function openWechatPublish(record: any) {
    publishRecord.value = record;
    publishForm.value = { digest: '', auto_publish: false };
    publishVisible.value = true;
  }

  async function confirmPublish() {
    if (!publishRecord.value) return;
    publishing.value = true;
    try {
      await axios.post('/wechat/publish', {
        article_id: publishRecord.value.articleId,
        auto_publish: publishForm.value.auto_publish,
        digest: publishForm.value.digest,
      }, { timeout: 30000 });
      Message.success('已加入发布队列，结果将通过消息中心通知');
      publishVisible.value = false;
    } catch {
      // 错误信封由拦截器统一提示
    } finally {
      publishing.value = false;
    }
  }
</script>

<style scoped>
  .publish-hint {
    margin-left: 12px;
    font-size: 12px;
    color: var(--color-text-3);
  }
</style>
