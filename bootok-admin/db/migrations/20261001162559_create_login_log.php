<?php

use Phinx\Migration\AbstractMigration;

/**
 * 登录日志：成功与失败都记录（安全审计 + 在线追溯）
 */
final class CreateLoginLog extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_login_log` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`   INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '成功时关联管理员',
    `admin_name` VARCHAR(50)  NOT NULL DEFAULT '' COMMENT '尝试的用户名',
    `status`     TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1成功 0失败',
    `ip`         VARCHAR(45)  NOT NULL DEFAULT '',
    `user_agent` VARCHAR(255) NOT NULL DEFAULT '',
    `message`    VARCHAR(100) NOT NULL DEFAULT '' COMMENT '失败原因',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_id` (`admin_id`),
    KEY `idx_created_at` (`created_at`)
) ENGINE = InnoDB COMMENT '登录日志';
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sys_login_log`');
    }
}
