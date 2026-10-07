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
  'menu.system.online': '在线会话',
  'menu.system.dict': '数据字典',
  'menu.system.task': '计划任务',
  'menu.system.message': '消息中心',
  'menu.system.backup': '数据备份',
  'menu.system.monitor': '系统监控',
  'menu.system.log': '运行日志',
  'menu.security': '安全审计',
  'menu.security.log': '操作日志',
  'menu.security.loginLog': '登录日志',
  'menu.resource': '附件管理',
  'menu.resource.attachment': '附件列表',
  'menu.resource.storageChannel': '存储渠道',
  'menu.ssl': 'SSL 证书',
  'menu.ssl.cert': '证书列表',
  'menu.ssl.deploy': '部署目标',
  'menu.ssl.dns': 'DNS 凭据',
  'menu.mail': '邮件管理',
  'menu.mail.channel': '邮件渠道',
  'menu.mail.template': '邮件模板',
  'menu.mail.log': '发送记录',
  'menu.auth': '权限管理',
  'menu.auth.admin': '管理员',
  'menu.auth.dept': '部门管理',
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
