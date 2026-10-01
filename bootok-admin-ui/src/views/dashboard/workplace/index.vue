<template>
  <div class="container">
    <a-card :bordered="false">
      <a-space direction="vertical" fill size="medium">
        <a-typography-title :heading="3">
          你好，{{ userStore.name ?? '管理员' }}
        </a-typography-title>
        <a-typography-text type="secondary">
          Bootok Admin 通用后台：模型注解 + 表单DSL + 单一资源控制器，新增业务模块只需一张表和一个带
          #[AutoResource] 注解的模型。
        </a-typography-text>
        <a-grid :cols="4" :col-gap="16" :row-gap="16">
          <a-grid-item>
            <a-card>
              <a-statistic title="管理员" :value="stats.admin">
                <template #suffix>
                  <icon-user />
                </template>
              </a-statistic>
            </a-card>
          </a-grid-item>
          <a-grid-item>
            <a-card>
              <a-statistic title="权限组" :value="stats.group">
                <template #suffix>
                  <icon-safe />
                </template>
              </a-statistic>
            </a-card>
          </a-grid-item>
          <a-grid-item>
            <a-card>
              <a-statistic title="文章" :value="stats.article">
                <template #suffix>
                  <icon-file />
                </template>
              </a-statistic>
            </a-card>
          </a-grid-item>
          <a-grid-item>
            <a-card>
              <a-statistic title="操作日志" :value="stats.log">
                <template #suffix>
                  <icon-history />
                </template>
              </a-statistic>
            </a-card>
          </a-grid-item>
        </a-grid>
      </a-space>
    </a-card>
  </div>
</template>

<script lang="ts" setup>
  import { onMounted, reactive } from 'vue';
  import { useUserStore } from '@/store';
  import BaseApi from '@/api/base';

  const userStore = useUserStore();

  const stats = reactive({
    admin: 0,
    group: 0,
    article: 0,
    log: 0,
  });

  onMounted(async () => {
    const items: Array<[keyof typeof stats, BaseApi]> = [
      ['admin', new BaseApi('Admin')],
      ['group', new BaseApi('Group')],
      ['article', new BaseApi('Article')],
      ['log', new BaseApi('OperationLog')],
    ];
    await Promise.all(
      items.map(async ([key, api]) => {
        const res = await api.getRows(1, {}, 1);
        stats[key] = res.data.total;
      })
    );
  });
</script>

<script lang="ts">
  export default { name: 'Workplace' };
</script>

<style scoped lang="less">
  .container {
    padding: 0;
  }
</style>
