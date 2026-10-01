---
layout: home

hero:
  name: Bootok Admin
  text: 通用后台管理系统
  tagline: 模型注解驱动资源 · 表单 DSL · purview 权限 · 单库多运行形态
  actions:
    - theme: brand
      text: 快速开始
      link: /guide/getting-started
    - theme: alt
      text: 新增一个业务模块
      link: /guide/new-module
    - theme: alt
      text: 系统介绍
      link: /guide/introduction

features:
  - title: 模型即资源
    details: 一张表 + 一个带 #[AutoResource] 注解的模型，即获得路由、表单、校验挂载点与权限点的全部能力，无需编写控制器。
  - title: 表单 DSL
    details: 后端用面向对象字段类声明表单（Text/Select/Editor…），序列化协议与前端通用 CRUD 组件一一对应，新增字段只改一处。
  - title: purview 权限
    details: 权限组存「前端路由 name + 按钮权限点」，一份数据同时驱动菜单过滤、路由守卫、按钮显隐与后端接口鉴权，后台无需配置任何菜单地址。
  - title: 单库多运行形态
    details: 管理端(8787)、用户端(8788)、计划任务是同一份代码的三个进程组，SERVER_ROLE 环境变量切换部署单元，模型零冗余、弹性镜像天然无任务。
  - title: 安全内建
    details: 登录防爆破、CORS 白名单、JWT 单点、读权限点、is_super 提权保护、滑动续期、统一异常信封，全部开箱即用。
  - title: 弹性部署就绪
    details: 端口门禁中间件隔离两端接口面；存储驱动可切 OSS；连接池容量公式与镜像制作要点开箱即查。
---
