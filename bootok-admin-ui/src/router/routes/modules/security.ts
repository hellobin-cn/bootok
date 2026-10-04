import { DEFAULT_LAYOUT } from '../base';
import { AppRouteRecordRaw } from '../types';

const SECURITY: AppRouteRecordRaw = {
  path: '/security',
  name: 'Security',
  component: DEFAULT_LAYOUT,
  meta: {
    locale: 'menu.security',
    title: '安全审计',
    requiresAuth: true,
    icon: 'icon-safe',
    order: 22,
  },
  children: [
    {
      path: 'operation-log',
      name: 'SystemLog',
      component: () => import('@/views/security/log.vue'),
      meta: {
        locale: 'menu.security.log',
        title: '操作日志',
        requiresAuth: true,
        perms: ['operation-log.rows'],
      },
    },
    {
      path: 'login-log',
      name: 'SystemLoginLog',
      component: () => import('@/views/security/login-log.vue'),
      meta: {
        locale: 'menu.security.loginLog',
        title: '登录日志',
        requiresAuth: true,
        perms: ['login-log.rows'],
      },
    },
  ],
};

export default SECURITY;
