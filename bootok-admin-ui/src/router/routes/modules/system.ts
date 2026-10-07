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
    order: 21,
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
      path: 'dict',
      name: 'SystemDict',
      component: () => import('@/views/system/dict.vue'),
      meta: {
        locale: 'menu.system.dict',
        title: '数据字典',
        requiresAuth: true,
        perms: ['dict-type.rows'],
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
    {
      path: 'message',
      name: 'SystemMessage',
      component: () => import('@/views/system/message.vue'),
      meta: {
        locale: 'menu.system.message',
        title: '消息中心',
        requiresAuth: true,
      },
    },
    {
      path: 'backup',
      name: 'SystemBackup',
      component: () => import('@/views/system/backup.vue'),
      meta: {
        locale: 'menu.system.backup',
        title: '数据备份',
        requiresAuth: true,
      },
    },
    {
      path: 'monitor',
      name: 'SystemMonitor',
      component: () => import('@/views/system/monitor.vue'),
      meta: {
        locale: 'menu.system.monitor',
        title: '系统监控',
        requiresAuth: true,
      },
    },
  ],
};

export default SYSTEM;
