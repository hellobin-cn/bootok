import { DEFAULT_LAYOUT } from '../base';
import { AppRouteRecordRaw } from '../types';

const SSL: AppRouteRecordRaw = {
  path: '/ssl',
  name: 'Ssl',
  component: DEFAULT_LAYOUT,
  meta: {
    locale: 'menu.ssl',
    title: 'SSL 证书',
    requiresAuth: true,
    icon: 'icon-safe',
    order: 4,
  },
  children: [
    {
      path: 'cert',
      name: 'SslCert',
      component: () => import('@/views/ssl/cert.vue'),
      meta: {
        locale: 'menu.ssl.cert',
        title: '证书列表',
        requiresAuth: true,
      },
    },
    {
      path: 'deploy',
      name: 'SslDeploy',
      component: () => import('@/views/ssl/deploy.vue'),
      meta: {
        locale: 'menu.ssl.deploy',
        title: '部署目标',
        requiresAuth: true,
        perms: ['ssl-deploy.rows'],
      },
    },
    {
      path: 'dns',
      name: 'SslDnsAccount',
      component: () => import('@/views/ssl/dns.vue'),
      meta: {
        locale: 'menu.ssl.dns',
        title: 'DNS 凭据',
        requiresAuth: true,
        perms: ['dns-account.rows'],
      },
    },
  ],
};

export default SSL;
