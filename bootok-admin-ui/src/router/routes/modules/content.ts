import { DEFAULT_LAYOUT } from '../base';
import { AppRouteRecordRaw } from '../types';

// 业务模块示例：新增业务页面只需在此定义路由 + meta.perms 权限点
const CONTENT: AppRouteRecordRaw = {
  path: '/content',
  name: 'Content',
  component: DEFAULT_LAYOUT,
  meta: {
    locale: 'menu.content',
    title: '内容管理',
    requiresAuth: true,
    icon: 'icon-bookmark',
    order: 10,
  },
  children: [
    {
      path: 'article',
      name: 'ContentArticle',
      component: () => import('@/views/content/article.vue'),
      meta: {
        locale: 'menu.content.article',
        title: '文章管理',
        requiresAuth: true,
        perms: [
          'article.create',
          'article.edit',
          'article.delete',
          'article.export',
          'article.import',
          'article.wechat_publish',
        ],
      },
    },
    {
      path: 'notice',
      name: 'ContentNotice',
      component: () => import('@/views/content/notice.vue'),
      meta: {
        locale: 'menu.content.notice',
        title: '公告管理',
        requiresAuth: true,
        perms: ['notice.create', 'notice.edit', 'notice.delete'],
      },
    },
  ],
};

export default CONTENT;
