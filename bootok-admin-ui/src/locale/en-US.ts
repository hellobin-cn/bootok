import localeLogin from '@/views/login/locale/en-US';

import localeSuccess from '@/views/result/success/locale/en-US';
import localeError from '@/views/result/error/locale/en-US';

import locale403 from '@/views/exception/403/locale/en-US';
import locale404 from '@/views/exception/404/locale/en-US';
import locale500 from '@/views/exception/500/locale/en-US';

import localeSettings from './en-US/settings';

export default {
  'menu.dashboard': 'Dashboard',
  'menu.dashboard.workplace': 'Workplace',
  'menu.system': 'System',
  'menu.system.config': 'Config',
  'menu.system.setting': 'Setting',
  'menu.system.online': 'Online Sessions',
  'menu.system.dict': 'Data Dictionary',
  'menu.system.task': 'Task',
  'menu.security': 'Security Audit',
  'menu.security.log': 'Operation Log',
  'menu.security.loginLog': 'Login Log',
  'menu.resource': 'Attachment',
  'menu.resource.attachment': 'Attachment List',
  'menu.resource.storageChannel': 'Storage Channels',
  'menu.ssl': 'SSL Certificates',
  'menu.ssl.cert': 'Certificates',
  'menu.ssl.deploy': 'Deploy Targets',
  'menu.ssl.dns': 'DNS Accounts',
  'menu.mail': 'Mail',
  'menu.mail.channel': 'Mail Channels',
  'menu.mail.template': 'Mail Templates',
  'menu.mail.log': 'Send Logs',
  'menu.auth': 'Auth',
  'menu.auth.admin': 'Admin',
  'menu.auth.group': 'Group',
  'menu.auth.groupEdit': 'Edit Group',
  'menu.content': 'Content',
  'menu.content.article': 'Article',
  'menu.content.notice': 'Notice',
  'navbar.action.locale': 'Switch to English',
  'messageBox.logout': 'Logout',
  ...localeSettings,
  ...localeLogin,
  ...localeSuccess,
  ...localeError,
  ...locale403,
  ...locale404,
  ...locale500,
};
