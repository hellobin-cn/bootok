<?php

use Phinx\Migration\AbstractMigration;

/**
 * 站内消息：系统事件 → 管理员的通知收件箱（任务失败/证书异常/安全告警）
 * 点对点（receiver_admin_id），已读状态收件人自查自改
 */
final class CreateMessage extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_message` (
    `message_id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `receiver_admin_id` INT UNSIGNED NOT NULL COMMENT '收件管理员',
    `title`             VARCHAR(200) NOT NULL COMMENT '标题',
    `content`           VARCHAR(2000) NOT NULL DEFAULT '' COMMENT '明细',
    `type`              VARCHAR(20) NOT NULL DEFAULT 'notice' COMMENT 'notice通知/task任务/ssl证书/security安全',
    `link`              VARCHAR(255) NOT NULL DEFAULT '' COMMENT '前端跳转路径',
    `is_read`           TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `read_at`           DATETIME NULL DEFAULT NULL,
    `created_at`        DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`message_id`),
    KEY `idx_receiver_read` (`receiver_admin_id`, `is_read`)
) ENGINE = InnoDB COMMENT '站内消息';
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sys_message`');
    }
}
