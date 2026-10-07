import { watch } from 'vue';
import { useI18n } from 'vue-i18n';
import axios from 'axios';
import { useAppStore } from '@/store';
import { setDark } from '@/hooks/theme';

// 与后端 UserPrefController 白名单一致；hideMenu 由响应式钩子按窗口宽度驱动，禁止同步
const STORE_KEYS = [
  'navbar',
  'menu',
  'topMenu',
  'footer',
  'tabBar',
  'menuWidth',
  'colorWeak',
  'menuCollapse',
];

const SAVE_DEBOUNCE_MS = 1000;

let inited = false;

/**
 * 登录后初始化（default-layout 挂载时调用一次）：
 * 拉取账号偏好覆盖本地渲染，之后 appStore/locale 变更防抖同步到服务器；
 * 保存失败静默（localStorage 兜底），账号偏好以最后一次保存为准
 */
export function initPrefSync(): void {
  if (inited) {
    return;
  }
  inited = true;
  const appStore = useAppStore();
  const { locale } = useI18n();

  const applyColorWeak = (value: boolean) => {
    document.body.style.filter = value ? 'invert(80%)' : 'none';
  };

  const save = () => {
    const prefs: Record<string, unknown> = {
      theme: appStore.theme,
      locale: locale.value,
    };
    for (const key of STORE_KEYS) {
      prefs[key] = (appStore as Record<string, unknown>)[key];
    }
    axios.put('/user/pref', { prefs }).catch(() => {});
  };

  let timer = 0;

  const scheduleSave = () => {
    window.clearTimeout(timer);
    timer = window.setTimeout(save, SAVE_DEBOUNCE_MS);
  };

  const startWatching = () => {
    appStore.$subscribe(scheduleSave);
    watch(locale, scheduleSave);
  };

  const apply = (prefs: Record<string, unknown>) => {
    if (prefs.theme) {
      setDark(prefs.theme === 'dark');
    }
    if (typeof prefs.locale === 'string' && prefs.locale !== locale.value) {
      locale.value = prefs.locale;
      localStorage.setItem('arco-locale', prefs.locale);
    }
    const rest: Record<string, unknown> = {};
    for (const key of STORE_KEYS) {
      if (prefs[key] !== undefined) {
        rest[key] = prefs[key];
      }
    }
    if (prefs.colorWeak !== undefined) {
      applyColorWeak(Boolean(prefs.colorWeak));
    }
    if (Object.keys(rest).length) {
      appStore.updateSettings(rest);
    }
  };

  // 先落账号偏好再开始监听，避免加载窗口期把本地默认值回写覆盖服务器
  axios
    .get('/user/pref')
    .then(({ data }: any) => {
      apply(data ?? {});
    })
    .catch(() => {})
    .finally(() => startWatching());
}
