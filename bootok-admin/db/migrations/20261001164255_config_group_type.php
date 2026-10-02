<?php

use Phinx\Migration\AbstractMigration;

/**
 * 配置中心：分组 + 类型
 * group 为 MySQL 保留字，全链路使用反引号
 */
final class ConfigGroupType extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `sys_config`
            ADD COLUMN `group` VARCHAR(30) NOT NULL DEFAULT 'site' COMMENT '分组，设置页按此分Tab' AFTER `title`,
            ADD COLUMN `type` VARCHAR(20) NOT NULL DEFAULT 'text' COMMENT 'text/int/bool/json' AFTER `group`");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `sys_config` DROP COLUMN `group`, DROP COLUMN `type`");
    }
}
