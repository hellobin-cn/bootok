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
    order: 3,
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
        perms: ['article.create', 'article.edit', 'article.delete'],
      },
    },
  ],
};

export default CONTENT;
