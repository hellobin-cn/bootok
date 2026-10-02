<?php

use Phinx\Migration\AbstractMigration;

/**
 * 基线种子（幂等：已存在则跳过）
 * 默认超管 admin/admin123
 */
final class BaselineSeed extends AbstractMigration
{
    public function up(): void
    {
        if ($this->fetchRow('SELECT COUNT(*) AS c FROM `sys_admin`')['c'] > 0) {
            return;
        }
        $this->execute("INSERT INTO `sys_group` (`group_id`, `group_name`, `purview`) VALUES (1, '超级管理员', '[]')");
        $this->execute("INSERT INTO `sys_admin` (`admin_id`, `admin_name`, `admin_password`, `group_id`, `is_super`) VALUES (1, 'admin', '\$2y\$12\$owN6ykmXj98HmLTl54Lc7u3OSXzwZK3KXuHtxw7ojRklqAD9pzoEe', 1, 1)");
        $this->execute("INSERT INTO `sys_config` (`name`, `title`, `value`, `sort`) VALUES ('site_name', '站点名称', 'Bootok Admin', 100)");
    }

    public function down(): void
    {
    }
}
