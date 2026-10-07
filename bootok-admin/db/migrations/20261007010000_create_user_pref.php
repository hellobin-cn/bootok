<?php

use Phinx\Migration\AbstractMigration;

/**
 * 个人界面偏好（页面配置抽屉/主题/语言）：一管理员一行 JSON 整体读写
 * 明确排除 hideMenu（响应式钩子按窗口宽度驱动）与运行时状态
 */
final class CreateUserPref extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_user_pref` (
    `admin_id`   INT UNSIGNED NOT NULL COMMENT '管理员',
    `prefs`      JSON NOT NULL COMMENT '界面偏好（白名单键值）',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`admin_id`)
) ENGINE = InnoDB COMMENT '个人界面偏好';
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sys_user_pref`');
    }
}
