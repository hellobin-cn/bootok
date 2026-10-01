import { DEFAULT_LAYOUT } from '../base';
import { AppRouteRecordRaw } from '../types';

const AUTH: AppRouteRecordRaw = {
  path: '/auth',
  name: 'Auth',
  component: DEFAULT_LAYOUT,
  meta: {
    locale: 'menu.auth',
    title: '权限管理',
    requiresAuth: true,
    icon: 'icon-safe',
    order: 2,
  },
  children: [
    {
      path: 'admin',
      name: 'AuthAdmin',
      component: () => import('@/views/auth/admin.vue'),
      meta: {
        locale: 'menu.auth.admin',
        title: '管理员',
        requiresAuth: true,
        perms: [
          'admin.rows',
          'admin.create',
          'admin.edit',
          'admin.delete',
        ],
      },
    },
    {
      path: 'group',
      name: 'AuthGroup',
      component: () => import('@/views/auth/group-list.vue'),
      meta: {
        locale: 'menu.auth.group',
        title: '权限组',
        requiresAuth: true,
        perms: ['group.create', 'group.edit', 'group.delete'],
      },
    },
    {
      path: 'group-edit',
      name: 'AuthGroupEdit',
      component: () => import('@/views/auth/group-edit.vue'),
      meta: {
        locale: 'menu.auth.groupEdit',
        title: '编辑权限组',
        requiresAuth: true,
        hideInMenu: true,
        activeMenu: 'AuthGroup',
      },
    },
  ],
};

export default AUTH;
