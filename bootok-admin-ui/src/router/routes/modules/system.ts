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
      path: 'setting',
      name: 'SystemSetting',
      component: () => import('@/views/system/setting.vue'),
      meta: {
        locale: 'menu.system.setting',
        title: '参数设置',
        requiresAuth: true,
        perms: ['config.save'],
      },
    },
    {
      path: 'online',
      name: 'SystemOnline',
      component: () => import('@/views/system/online.vue'),
      meta: {
        locale: 'menu.system.online',
        title: '在线会话',
        requiresAuth: true,
      },
    },
    {
      path: 'task',
      name: 'SystemTask',
      component: () => import('@/views/system/task.vue'),
      meta: {
        locale: 'menu.system.task',
        title: '计划任务',
        requiresAuth: true,
        perms: ['task.run'],
      },
    },
  ],
};

export default SYSTEM;
