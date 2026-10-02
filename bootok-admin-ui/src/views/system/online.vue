<template>
  <a-card title="在线会话" :bordered="false">
    <a-alert v-if="!userStore.isSuper" type="warning">
      仅超级管理员可管理在线会话
    </a-alert>
    <a-table
      v-else
      :data="list"
      :loading="loading"
      :pagination="false"
    >
      <template #columns>
        <a-table-column title="ID" data-index="admin_id" :width="80" />
        <a-table-column title="管理员" data-index="admin_name" :width="160" />
        <a-table-column title="角色" :width="120">
          <template #cell="{ record }">
            <a-tag v-if="record.is_super === 1" color="orangered">超级管理员</a-tag>
            <a-tag v-else>普通管理员</a-tag>
          </template>
        </a-table-column>
        <a-table-column title="登录时间" data-index="login_time" :width="200" />
        <a-table-column title="操作" :width="140">
          <template #cell="{ record }">
            <a-popconfirm
              content="确认强制下线该管理员？"
              type="warning"
              @ok="kick(record.admin_id)"
            >
              <a-button size="small" status="danger">强制下线</a-button>
            </a-popconfirm>
          </template>
        </a-table-column>
      </template>
    </a-table>
  </a-card>
</template>

<script lang="ts" setup>
  import { onMounted, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import { useUserStore } from '@/store';
  import { getToken } from '@/utils/auth';

  const userStore = useUserStore();
  const loading = ref(false);
  const list = ref<any[]>([]);

  async function load() {
    loading.value = true;
    try {
      const res = await axios.get('/admin/online', {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
      });
      list.value = res.data ?? [];
    } catch (e) {
      // 拦截器已提示
    } finally {
      loading.value = false;
    }
  }

  async function kick(adminId: number) {
    try {
      const res: any = await axios.post(
        `/admin/online/${adminId}/kick`,
        {},
        {
          baseURL: import.meta.env.VITE_API_BASE_URL as string,
          headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        }
      );
      if (res.code === '0') {
        Message.success(res.message || '已下线');
        load();
      }
    } catch (e) {
      // 拦截器已提示
    }
  }

  onMounted(load);
</script>

<style scoped></style>
