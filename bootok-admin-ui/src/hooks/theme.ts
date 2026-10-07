import { useDark, useToggle } from '@vueuse/core';
import useAppStore from '@/store/modules/app';

let toggle: (() => boolean) | null = null;
let setMode: ((dark: boolean) => void) | null = null;

/**
 * 主题初始化在 App.vue 调用一次：登录页等无导航栏页面也要应用主题（硬刷登录页跟随已存主题/系统偏好）。
 * 登录横幅底色恒定深蓝、文字用固定浅色，暗黑下不再发灰——勿再把 initialValue 钉成 light 绕过。
 */
export function initTheme(): void {
  if (toggle) {
    return;
  }
  const isDark = useDark({
    selector: 'body',
    attribute: 'arco-theme',
    valueDark: 'dark',
    valueLight: 'light',
    storageKey: 'arco-theme',
    onChanged(dark: boolean) {
      useAppStore().toggleTheme(dark);
    },
  });
  toggle = useToggle(isDark);
  setMode = (dark: boolean) => {
    isDark.value = dark;
  };
}

/** 导航栏主题切换按钮入口 */
export function toggleDark(): void {
  toggle?.();
}

/** 直接设定主题（账号偏好落地时使用），storage 同步更新 */
export function setDark(dark: boolean): void {
  setMode?.(dark);
}
