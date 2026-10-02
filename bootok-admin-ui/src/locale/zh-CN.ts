import localeLogin from '@/views/login/locale/zh-CN';

import localeSuccess from '@/views/result/success/locale/zh-CN';
import localeError from '@/views/result/error/locale/zh-CN';

import locale403 from '@/views/exception/403/locale/zh-CN';
import locale404 from '@/views/exception/404/locale/zh-CN';
import locale500 from '@/views/exception/500/locale/zh-CN';

import localeSettings from './zh-CN/settings';

export default {
  'menu.dashboard': '仪表盘',
  'menu.dashboard.workplace': '工作台',
  'menu.system': '系统管理',
  'menu.system.config': '系统配置',
  'menu.system.setting': '参数设置',
  'menu.system.log': '操作日志',
  'menu.system.loginLog': '登录日志',
  'menu.system.online': '在线会话',
  'menu.system.attachment': '附件管理',
  'menu.system.task': '计划任务',
  'menu.auth': '权限管理',
  'menu.auth.admin': '管理员',
  'menu.auth.group': '权限组',
  'menu.auth.groupEdit': '编辑权限组',
  'menu.content': '内容管理',
  'menu.content.article': '文章管理',
  'menu.content.notice': '公告管理',
  'navbar.action.locale': '切换为中文',
  'messageBox.logout': '退出登录',
  ...localeSettings,
  ...localeLogin,
  ...localeSuccess,
  ...localeError,
  ...locale403,
  ...locale404,
  ...locale500,
};
