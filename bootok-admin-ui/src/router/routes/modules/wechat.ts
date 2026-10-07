import { DEFAULT_LAYOUT } from '../base';
import { AppRouteRecordRaw } from '../types';

const WECHAT: AppRouteRecordRaw = {
  path: '/wechat',
  name: 'Wechat',
  component: DEFAULT_LAYOUT,
  meta: {
    locale: 'menu.wechat',
    title: '公众号管理',
    requiresAuth: true,
    icon: 'icon-wechat',
    order: 15,
  },
  children: [
    {
      path: 'settings',
      name: 'WechatSettings',
      component: () => import('@/views/wechat/settings.vue'),
      meta: {
        locale: 'menu.wechat.settings',
        title: '公众号设置',
        requiresAuth: true,
      },
    },
    {
      path: 'publishes',
      name: 'WechatPublishes',
      component: () => import('@/views/wechat/publishes.vue'),
      meta: {
        locale: 'menu.wechat.publishes',
        title: '发布记录',
        requiresAuth: true,
      },
    },
  ],
};

export default WECHAT;
