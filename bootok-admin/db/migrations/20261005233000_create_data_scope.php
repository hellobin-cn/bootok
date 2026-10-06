<?php

use Phinx\Migration\AbstractMigration;

/**
 * 数据权限：部门树 + 管理员挂部门 + 权限组数据范围
 *
 * sys_dept.path 存祖先链含自身（如 "1/3/7"），子树查询走前缀匹配；
 * sys_group.data_scope 四级：1 全部 / 2 本部门及以下 / 3 仅本部门 / 4 仅本人，
 * 默认 1 保证存量权限组行为零变化。
 */
final class CreateDataScope extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_dept` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `parent_id`  INT UNSIGNED NULL DEFAULT NULL COMMENT '父部门，NULL 为根',
    `name`       VARCHAR(100) NOT NULL COMMENT '部门名称',
    `path`       VARCHAR(1000) NOT NULL COMMENT '祖先链含自身，如 1/3/7',
    `sort`       INT NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_path` (`path`(255)),
    KEY `idx_parent` (`parent_id`)
) ENGINE = InnoDB COMMENT '部门树';
SQL);
        $this->execute("ALTER TABLE `sys_admin` ADD COLUMN `dept_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属部门（0 未分配）' AFTER `group_id`");
        $this->execute("ALTER TABLE `sys_group` ADD COLUMN `data_scope` TINYINT NOT NULL DEFAULT 1 COMMENT '数据范围 1全部/2本部门及以下/3仅本部门/4仅本人' AFTER `purview`");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `sys_group` DROP COLUMN `data_scope`");
        $this->execute("ALTER TABLE `sys_admin` DROP COLUMN `dept_id`");
        $this->execute('DROP TABLE IF EXISTS `sys_dept`');
    }
}
