<template>
  <div class="page-container">
    <Breadcrumb :items="breadcrumbItems" />
    <router-view v-slot="{ Component, route }">
      <transition name="fade" mode="out-in" appear>
        <component
          :is="Component"
          v-if="route.meta.ignoreCache"
          :key="route.fullPath"
        />
        <keep-alive v-else :include="cacheList">
          <component :is="Component" :key="route.fullPath" />
        </keep-alive>
      </transition>
    </router-view>
  </div>
</template>

<script lang="ts" setup>
  import { computed } from 'vue';
  import { useRoute } from 'vue-router';
  import { useTabBarStore } from '@/store';
  import Breadcrumb from '@/components/breadcrumb/index.vue';

  const route = useRoute();
  const tabBarStore = useTabBarStore();

  const cacheList = computed(() => tabBarStore.getCacheList);

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
