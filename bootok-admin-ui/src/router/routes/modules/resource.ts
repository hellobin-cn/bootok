import { DEFAULT_LAYOUT } from '../base';
import { AppRouteRecordRaw } from '../types';

const RESOURCE: AppRouteRecordRaw = {
  path: '/resource',
  name: 'Resource',
  component: DEFAULT_LAYOUT,
  meta: {
    locale: 'menu.resource',
    title: '附件管理',
    requiresAuth: true,
    icon: 'icon-cloud',
    order: 13,
  },
  children: [
    {
      path: 'attachment',
      name: 'ResourceAttachment',
      component: () => import('@/views/resource/attachment.vue'),
      meta: {
        locale: 'menu.resource.attachment',
        title: '附件列表',
        requiresAuth: true,
        perms: ['attachment.rows'],
      },
    },
    {
      path: 'storage-channel',
      name: 'ResourceStorageChannel',
      component: () => import('@/views/resource/storage-channel.vue'),
      meta: {
        locale: 'menu.resource.storageChannel',
        title: '存储渠道',
        requiresAuth: true,
        perms: ['storage-channel.rows'],
      },
    },
  ],
};

export default RESOURCE;
