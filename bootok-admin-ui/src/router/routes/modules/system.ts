import { DEFAULT_LAYOUT } from '../base';
import { AppRouteRecordRaw } from '../types';

const SYSTEM: AppRouteRecordRaw = {
  path: '/system',
  name: 'System',
  component: DEFAULT_LAYOUT,
  meta: {
    locale: 'menu.system',
    title: '系统管理',
    requiresAuth: true,
    icon: 'icon-settings',
    order: 1,
  },
  children: [
    {
      path: 'config',
      name: 'SystemConfig',
      component: () => import('@/views/system/config.vue'),
      meta: {
        locale: 'menu.system.config',
        title: '系统配置',
        requiresAuth: true,
        perms: [
          'system-config.rows',
          'system-config.create',
          'system-config.edit',
          'system-config.delete',
        ],
      },
    },
    {
      path: 'log',
      name: 'SystemLog',
      component: () => import('@/views/system/log.vue'),
      meta: {
        locale: 'menu.system.log',
        title: '操作日志',
        requiresAuth: true,
        perms: ['operation-log.rows'],
      },
    },
  ],
};

export default SYSTEM;
