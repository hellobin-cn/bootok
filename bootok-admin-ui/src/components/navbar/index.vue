<template>
  <div class="navbar">
    <div class="left-side">
      <a-space>
        <img
          alt="logo"
          src="//p3-armor.byteimg.com/tos-cn-i-49unhts6dw/dfdba5317c0c20ce20e64fac803d52bc.svg~tplv-49unhts6dw-image.image"
        />
        <a-typography-title
          :style="{ margin: 0, fontSize: '18px' }"
          :heading="5"
        >
          Bootok Admin
        </a-typography-title>
        <icon-menu-fold
          v-if="!topMenu && appStore.device === 'mobile'"
          style="font-size: 22px; cursor: pointer"
          @click="toggleDrawerMenu"
        />
      </a-space>
    </div>
    <div class="center-side">
      <Menu v-if="topMenu" />
    </div>
    <ul class="right-side">
      <li>
        <a-tooltip :content="$t('settings.search')">
          <a-button class="nav-btn" type="outline" :shape="'circle'">
            <template #icon>
              <icon-search />
            </template>
          </a-button>
        </a-tooltip>
      </li>
      <li>
        <a-tooltip :content="$t('settings.language')">
          <a-button
            class="nav-btn"
            type="outline"
            :shape="'circle'"
            @click="setDropDownVisible"
          >
            <template #icon>
              <icon-language />
            </template>
          </a-button>
        </a-tooltip>
        <a-dropdown trigger="click" @select="changeLocale as any">
          <div ref="triggerBtn" class="trigger-btn"></div>
          <template #content>
            <a-doption
              v-for="item in locales"
              :key="item.value"
              :value="item.value"
            >
              <template #icon>
                <icon-check v-show="item.value === currentLocale" />
              </template>
              {{ item.label }}
            </a-doption>
          </template>
        </a-dropdown>
      </li>
      <li>
        <a-tooltip
          :content="
            theme === 'light'
              ? $t('settings.navbar.theme.toDark')
              : $t('settings.navbar.theme.toLight')
          "
        >
          <a-button
            class="nav-btn"
            type="outline"
            :shape="'circle'"
            @click="handleToggleTheme"
          >
            <template #icon>
              <icon-moon-fill v-if="theme === 'dark'" />
              <icon-sun-fill v-else />
            </template>
          </a-button>
        </a-tooltip>
      </li>
      <li>
        <a-tooltip
          :content="
            isFullscreen
              ? $t('settings.navbar.screen.toExit')
              : $t('settings.navbar.screen.toFull')
          "
        >
          <a-button
            class="nav-btn"
            type="outline"
            :shape="'circle'"
            @click="toggleFullScreen"
          >
            <template #icon>
              <icon-fullscreen-exit v-if="isFullscreen" />
              <icon-fullscreen v-else />
            </template>
          </a-button>
        </a-tooltip>
      </li>
      <li>
        <a-tooltip :content="$t('settings.title')">
          <a-button
            class="nav-btn"
            type="outline"
            :shape="'circle'"
            @click="setVisible"
          >
            <template #icon>
              <icon-settings />
            </template>
          </a-button>
        </a-tooltip>
      </li>
      <li>
        <a-popover
          trigger="click"
          position="br"
          content-class="message-popover"
          @popup-visible-change="onMessagePop"
        >
          <a-badge :count="unreadCount" :max-count="99" :offset="[2, 4]">
            <a-button class="nav-btn" type="outline" :shape="'circle'">
              <template #icon>
                <icon-bell />
              </template>
            </a-button>
          </a-badge>
          <template #content>
            <div class="message-panel">
              <div class="message-panel-head">
                <span>未读消息（{{ unreadCount }}）</span>
                <a-space size="mini">
                  <a-link @click="readAllMessages">全部已读</a-link>
                  <a-link @click="goMessageCenter">查看全部</a-link>
                </a-space>
              </div>
              <div
                v-for="item in recentMessages"
                :key="item.message_id"
                class="message-item"
                @click="openMessage(item)"
              >
                <div class="message-item-line">
                  <a-tag size="small" :color="messageTypeColor(item.type)">
                    {{ messageTypeText(item.type) }}
                  </a-tag>
                  <span
                    class="message-item-title"
                    :class="{ unread: !item.is_read }"
                  >
                    {{ item.title }}
                  </span>
                </div>
                <div class="message-item-time">{{ item.created_at }}</div>
              </div>
              <a-empty
                v-if="!recentMessages.length"
                description="暂无消息"
                style="padding: 12px 0"
              />
            </div>
          </template>
        </a-popover>
      </li>
      <li>
        <a-dropdown trigger="click">
          <a-avatar
            :size="32"
            :style="{ marginRight: '8px', cursor: 'pointer' }"
          >
            {{ (userStore.name || 'A').substring(0, 1) }}
          </a-avatar>
          <template #content>
            <a-doption>
              <a-space @click="passwordVisible = true">
                <icon-edit />
                <span>修改密码</span>
              </a-space>
            </a-doption>
            <a-doption>
              <a-space @click="handleLogout">
                <icon-export />
                <span>
                  {{ $t('messageBox.logout') }}
                </span>
              </a-space>
            </a-doption>
          </template>
        </a-dropdown>
      </li>
    </ul>
    <a-modal
      v-model:visible="passwordVisible"
      title="修改密码"
      :unmount-on-close="true"
      @ok="handleUpdatePassword"
      @cancel="passwordVisible = false"
    >
      <a-form :model="passwordForm" label-align="right" auto-label-width>
        <a-form-item label="原密码" required>
          <a-input-password v-model="passwordForm.oldPassword" />
        </a-form-item>
        <a-form-item label="新密码" required help="不少于6位">
          <a-input-password v-model="passwordForm.newPassword" />
        </a-form-item>
        <a-form-item label="确认密码" required>
          <a-input-password v-model="passwordForm.rePassword" />
        </a-form-item>
      </a-form>
    </a-modal>
  </div>
</template>

<script lang="ts" setup>
  import { computed, inject, onMounted, onUnmounted, ref } from 'vue';
  import { Message } from '@arco-design/web-vue';
  import { useDark, useToggle, useFullscreen } from '@vueuse/core';
  import { useRouter } from 'vue-router';
  import axios from 'axios';
  import { useAppStore, useUserStore } from '@/store';
  import { LOCALE_OPTIONS } from '@/locale';
  import useLocale from '@/hooks/locale';
  import useUser from '@/hooks/user';
  import { updatePassword } from '@/api/user';
  import Menu from '@/components/menu/index.vue';

  const appStore = useAppStore();
  const userStore = useUserStore();
  const { logout } = useUser();
  const { changeLocale, currentLocale } = useLocale();
  const { isFullscreen, toggle: toggleFullScreen } = useFullscreen();
  const locales = [...LOCALE_OPTIONS];
  const passwordVisible = ref(false);
  const passwordForm = ref({
    oldPassword: '',
    newPassword: '',
    rePassword: '',
  });
  const theme = computed(() => {
    return appStore.theme;
  });
  const topMenu = computed(() => appStore.topMenu && appStore.menu);
  const isDark = useDark({
    selector: 'body',
    attribute: 'arco-theme',
    valueDark: 'dark',
    valueLight: 'light',
    // 固定默认浅色：不设 initialValue 时 VueUse 跟随系统深浅色，
    // 深色系统环境下登录过一次 body 就被钉上 dark，退出后登录页变灰
    initialValue: 'light',
    storageKey: 'arco-theme',
    onChanged(dark: boolean) {
      // overridden default behavior
      appStore.toggleTheme(dark);
    },
  });
  const toggleTheme = useToggle(isDark);
  const handleToggleTheme = () => {
    toggleTheme();
  };
  const setVisible = () => {
    appStore.updateSettings({ globalSettings: true });
  };
  const triggerBtn = ref();
  const handleLogout = () => {
    logout();
  };
  const setDropDownVisible = () => {
    const event = new MouseEvent('click', {
      view: window,
      bubbles: true,
      cancelable: true,
    });
    triggerBtn.value.dispatchEvent(event);
  };
  const handleUpdatePassword = async () => {
    if (
      !passwordForm.value.oldPassword ||
      !passwordForm.value.newPassword ||
      !passwordForm.value.rePassword
    ) {
      Message.error('请填写完整');
      return;
    }
    const res = await updatePassword({
      old_password: passwordForm.value.oldPassword,
      new_password: passwordForm.value.newPassword,
      re_password: passwordForm.value.rePassword,
    });
    if (res.code === '0') {
      Message.success('密码已更新，请重新登录');
      passwordVisible.value = false;
      logout();
    }
  };
  const toggleDrawerMenu = inject('toggleDrawerMenu') as () => void;

  // ===== 站内消息：铃铛未读数 30s 轮询 + 最近消息下拉 =====
  const router = useRouter();
  const unreadCount = ref(0);
  const recentMessages = ref<any[]>([]);
  let messageTimer = 0;

  const messageTypeMap: Record<string, { text: string; color: string }> = {
    notice: { text: '通知', color: 'gray' },
    task: { text: '任务', color: 'orangered' },
    ssl: { text: '证书', color: 'arcoblue' },
    security: { text: '安全', color: 'red' },
  };
  const messageTypeText = (type: string) => messageTypeMap[type]?.text ?? '通知';
  const messageTypeColor = (type: string) => messageTypeMap[type]?.color ?? 'gray';

  async function loadMessages() {
    try {
      const res: any = await axios.get('/message/latest');
      unreadCount.value = res.data?.unread ?? 0;
      recentMessages.value = res.data?.items ?? [];
    } catch {
      // 轮询失败静默（登录失效由拦截器统一处理）
    }
  }

  function onMessagePop(visible: boolean) {
    if (visible) {
      loadMessages();
    }
  }

  async function readAllMessages() {
    await axios.post('/message/read-all', {});
    await loadMessages();
    Message.success('已全部标记为已读');
  }

  async function openMessage(item: any) {
    if (!item.is_read) {
      await axios.post('/message/read', { ids: [item.message_id] });
      await loadMessages();
    }
    if (item.link) {
      router.push(item.link);
    }
  }

  function goMessageCenter() {
    router.push({ name: 'SystemMessage' });
  }

  onMounted(() => {
    loadMessages();
    messageTimer = window.setInterval(loadMessages, 30000);
  });
  onUnmounted(() => window.clearInterval(messageTimer));
</script>

<style scoped lang="less">
  .navbar {
    display: flex;
    justify-content: space-between;
    height: 100%;
    background-color: var(--color-bg-2);
    border-bottom: 1px solid var(--color-border);
  }

  .left-side {
    display: flex;
    align-items: center;
    padding-left: 20px;
  }

  .center-side {
    flex: 1;
  }

  .right-side {
    display: flex;
    padding-right: 20px;
    list-style: none;
    :deep(.locale-select) {
      border-radius: 20px;
    }
    li {
      display: flex;
      align-items: center;
      padding: 0 10px;
    }

    a {
      color: var(--color-text-1);
      text-decoration: none;
    }
    .nav-btn {
      border-color: rgb(var(--gray-2));
      color: rgb(var(--gray-8));
      font-size: 16px;
    }
    .trigger-btn,
    .ref-btn {
      position: absolute;
      bottom: 14px;
    }
    .trigger-btn {
      margin-left: 14px;
    }
  }
</style>

<style lang="less">
  .message-popover {
    .arco-popover-content {
      margin-top: 0;
    }
  }

  .message-panel {
    width: 320px;

    .message-panel-head {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0 4px 8px;
      font-weight: 500;
    }

    .message-item {
      padding: 8px 4px;
      border-top: 1px solid var(--color-border);
      cursor: pointer;

      &:hover {
        background: var(--color-fill-1);
      }

      .message-item-line {
        display: flex;
        align-items: center;
        gap: 6px;
      }

      .message-item-title {
        font-size: 13px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;

        &.unread {
          font-weight: 600;
        }
      }

      .message-item-time {
        font-size: 12px;
        color: var(--color-text-3);
        margin-top: 2px;
        padding-left: 2px;
      }
    }
  }
</style>
