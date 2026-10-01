import { defineConfig } from 'vitepress';

// https://vitepress.dev/zh/reference/site-config
export default defineConfig({
    lang: 'zh-CN',
    title: 'Bootok Admin',
    description: '通用后台管理系统：模型注解驱动资源 · 表单 DSL · purview 权限 · 单库多运行形态',
    ignoreDeadLinks: true,
    themeConfig: {
        siteTitle: 'Bootok Admin',
        nav: [
            { text: '指南', link: '/guide/introduction' },
            { text: '参考', link: '/reference/api-endpoints' }
        ],
        sidebar: {
            '/guide/': [
                {
                    text: '开始',
                    items: [
                        { text: '介绍', link: '/guide/introduction' },
                        { text: '快速开始', link: '/guide/getting-started' },
                        { text: '新增业务模块', link: '/guide/new-module' }
                    ]
                },
                {
                    text: '核心机制',
                    items: [
                        { text: '权限体系（purview）', link: '/guide/permission' },
                        { text: '表单 DSL', link: '/guide/form-dsl' },
                        { text: '查询协议', link: '/guide/query' },
                        { text: '前端开发指南', link: '/guide/frontend' }
                    ]
                },
                {
                    text: '工程',
                    items: [
                        { text: '命名规范', link: '/guide/naming' },
                        { text: '部署', link: '/guide/deployment' },
                        { text: '测试', link: '/guide/testing' }
                    ]
                }
            ],
            '/reference/': [
                {
                    text: '参考',
                    items: [
                        { text: '接口端点', link: '/reference/api-endpoints' }
                    ]
                }
            ]
        },
        search: {
            provider: 'local',
            options: {
                translations: {
                    button: { buttonText: '搜索文档', buttonAriaLabel: '搜索文档' },
                    modal: {
                        noResultsText: '没有找到结果',
                        resetButtonTitle: '清除查询条件',
                        footer: { selectText: '选择', navigateText: '切换' }
                    }
                }
            }
        },
        outline: { level: [2, 3], label: '本页目录' },
        docFooter: { prev: '上一篇', next: '下一篇' },
        returnToTopLabel: '回到顶部',
        sidebarMenuLabel: '菜单'
    }
});
