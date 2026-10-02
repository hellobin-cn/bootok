<template>
  <a-card title="参数设置" :bordered="false">
    <a-spin :loading="loading" style="width: 100%">
      <a-alert
        style="margin-bottom: 16px"
        type="info"
      >
        配置项的新增/删除在「系统配置」中管理；此处仅调整取值，保存后即时生效。
      </a-alert>
      <a-tabs v-if="groups.length" v-model:active-key="activeGroup">
        <a-tab-pane v-for="g in groups" :key="g" :title="g">
          <a-form :model="form" auto-label-width style="max-width: 720px">
            <a-form-item
              v-for="item in byGroup[g]"
              :key="item.name"
              :label="item.title"
            >
              <a-switch
                v-if="item.type === 'bool'"
                v-model="form[item.name]"
              />
              <a-input-number
                v-else-if="item.type === 'int'"
                v-model="form[item.name]"
                style="width: 240px"
              />
              <a-textarea
                v-else-if="item.type === 'json'"
                v-model="form[item.name]"
                :auto-size="{ minRows: 3, maxRows: 8 }"
                placeholder='{"key": "value"}'
              />
              <a-input v-else v-model="form[item.name]" />
              <template #help>{{ item.name }}</template>
            </a-form-item>
            <a-form-item>
              <a-button type="primary" :loading="saving === g" @click="saveGroup(g)">
                保存{{ g }}组
              </a-button>
            </a-form-item>
          </a-form>
        </a-tab-pane>
      </a-tabs>
      <a-empty v-else description="暂无配置项，请在「系统配置」中新增" />
    </a-spin>
  </a-card>
</template>

<script lang="ts" setup>
  import { computed, onMounted, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import axios from 'axios';
  import { getToken } from '@/utils/auth';

  interface ConfigItem {
    config_id: number;
    name: string;
    title: string;
    group: string;
    type: string;
    value: any;
  }

  const loading = ref(false);
  const saving = ref('');
  const items = ref<ConfigItem[]>([]);
  const form = ref<{ [key: string]: any }>({});
  const activeGroup = ref('');

  const byGroup = computed(() => {
    const map: { [group: string]: ConfigItem[] } = {};
    items.value.forEach((item) => {
      (map[item.group] ??= []).push(item);
    });
    return map;
  });

  const groups = computed(() => Object.keys(byGroup.value));

  async function load() {
    loading.value = true;
    try {
      const res = await axios.get('/config/settings', {
        baseURL: import.meta.env.VITE_API_BASE_URL as string,
        headers: { Authorization: `Bearer ${getToken() ?? ''}` },
      });
      items.value = res.data ?? [];
      const model: { [key: string]: any } = {};
      items.value.forEach((item) => {
        model[item.name] = item.type === 'json' && item.value !== null && typeof item.value !== 'string'
          ? JSON.stringify(item.value, null, 2)
          : item.value;
      });
      form.value = model;
      activeGroup.value = groups.value[0] ?? '';
    } catch (e) {
      // 拦截器已提示
    } finally {
      loading.value = false;
    }
  }

  async function saveGroup(group: string) {
    const groupItems = byGroup.value[group] ?? [];
    const payload: { [key: string]: any } = {};
    for (const item of groupItems) {
      let value = form.value[item.name];
      if (item.type === 'json') {
        try {
          value = JSON.parse(value);
        } catch (e) {
          Message.error(`配置项 ${item.title} 不是合法 JSON`);
          return;
        }
      }
      payload[item.name] = value;
    }
    saving.value = group;
    try {
      const res: any = await axios.post(
        '/config/settings',
        { items: payload },
        {
          baseURL: import.meta.env.VITE_API_BASE_URL as string,
          headers: { Authorization: `Bearer ${getToken() ?? ''}` },
        }
      );
      if (res.code === '0') {
        Message.success(res.message || '保存成功');
      }
    } catch (e) {
      // 拦截器已提示
    } finally {
      saving.value = '';
    }
  }

  onMounted(load);
</script>

<style scoped></style>
