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
  'menu.system.log': 'Operation Log',
  'menu.system.loginLog': 'Login Log',
  'menu.system.online': 'Online Sessions',
  'menu.system.attachment': 'Attachment',
  'menu.system.task': 'Task',
  'menu.system.ssl': 'SSL Certificates',
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
