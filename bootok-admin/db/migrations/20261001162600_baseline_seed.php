<?php

use Phinx\Migration\AbstractMigration;

/**
 * 基线种子（幂等：已存在则跳过）
 * 默认超管 admin/admin123；演示受限账号 editor/editor123（仅内容管理+文章增改）
 */
final class BaselineSeed extends AbstractMigration
{
    public function up(): void
    {
        if ($this->fetchRow('SELECT COUNT(*) AS c FROM `sys_admin`')['c'] > 0) {
            return;
        }
        $this->execute("INSERT INTO `sys_group` (`group_id`, `group_name`, `purview`) VALUES (1, '超级管理员', '[]')");
        $this->execute("INSERT INTO `sys_group` (`group_id`, `group_name`, `purview`) VALUES (2, '内容编辑', '[\"Content\",\"ContentArticle\",\"article.create\",\"article.edit\"]')");
        $this->execute("INSERT INTO `sys_admin` (`admin_id`, `admin_name`, `admin_password`, `group_id`, `is_super`) VALUES (1, 'admin', '\$2y\$12\$owN6ykmXj98HmLTl54Lc7u3OSXzwZK3KXuHtxw7ojRklqAD9pzoEe', 1, 1)");
        $this->execute("INSERT INTO `sys_admin` (`admin_id`, `admin_name`, `admin_password`, `group_id`, `is_super`) VALUES (2, 'editor', '\$2y\$12\$UmYMJJELLHdG1aSc4EEwr.TmdAZCjKjuKGVczYgq/SgeHytTvQqYK', 2, 0)");
        $this->execute("INSERT INTO `sys_config` (`name`, `title`, `value`, `sort`) VALUES ('site_name', '站点名称', 'Bootok Admin', 100), ('site_icp', '备案号', '', 90)");
        $this->execute("INSERT INTO `article` (`title`, `cover`, `content`, `status`, `sort`) VALUES ('欢迎使用 bootok-admin', '', '<p>这是一个通用后台管理示例文章，整套后台由模型注解与表单DSL驱动。</p>', 1, 100), ('第二个示例文章', '', '<p>新增业务模块只需：建表 + 写一个带 #[AutoResource] 注解的模型。</p>', 1, 90)");
    }

    public function down(): void
    {
    }
}
