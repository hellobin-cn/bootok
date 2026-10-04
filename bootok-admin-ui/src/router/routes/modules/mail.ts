import { DEFAULT_LAYOUT } from '../base';
import { AppRouteRecordRaw } from '../types';

const MAIL: AppRouteRecordRaw = {
  path: '/mail',
  name: 'Mail',
  component: DEFAULT_LAYOUT,
  meta: {
    locale: 'menu.mail',
    title: '邮件管理',
    requiresAuth: true,
    icon: 'icon-email',
    order: 7,
  },
  children: [
    {
      path: 'channel',
      name: 'MailChannel',
      component: () => import('@/views/mail/channel.vue'),
      meta: {
        locale: 'menu.mail.channel',
        title: '邮件渠道',
        requiresAuth: true,
        perms: ['mail-channel.rows'],
      },
    },
    {
      path: 'template',
      name: 'MailTemplate',
      component: () => import('@/views/mail/template.vue'),
      meta: {
        locale: 'menu.mail.template',
        title: '邮件模板',
        requiresAuth: true,
        perms: ['mail-template.rows'],
      },
    },
    {
      path: 'log',
      name: 'MailLog',
      component: () => import('@/views/mail/log.vue'),
      meta: {
        locale: 'menu.mail.log',
        title: '发送记录',
        requiresAuth: true,
        perms: ['mail-log.rows'],
      },
    },
  ],
};

export default MAIL;
