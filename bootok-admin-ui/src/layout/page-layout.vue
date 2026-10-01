<template>
  <div class="page-container">
    <Breadcrumb :items="breadcrumbItems" />
    <router-view v-slot="{ Component, route }">
      <!-- 不包 transition/keep-alive：二者对页面组件有单根要求，
           多根模板（如 crud 与弹窗平级）会静默白屏；key 只用 path，
           query 变化（如列表翻页写入 page）不重挂载页面 -->
      <component :is="Component" :key="route.path" />
    </router-view>
  </div>
</template>

<script lang="ts" setup>
  import { computed } from 'vue';
  import { useRoute } from 'vue-router';
  import Breadcrumb from '@/components/breadcrumb/index.vue';

  const route = useRoute();

  // 从路由匹配链自动生成面包屑（locale 键，由 Breadcrumb 组件翻译）
  const breadcrumbItems = computed(() =>
    route.matched
      .filter((item) => item.meta?.locale && item.name)
      .map((item) => item.meta!.locale as string)
  );
</script>

<style scoped lang="less">
  .page-container {
    padding: 0 20px 16px;
  }
</style>
