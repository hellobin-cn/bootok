<?php

use Phinx\Migration\AbstractMigration;

/**
 * 存储渠道：附件的存储后端可管理化（local/oss/cos），附件行记录所用渠道
 */
final class CreateStorageChannel extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_storage_channel` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(50) NOT NULL COMMENT '渠道名称',
    `type`       VARCHAR(20) NOT NULL DEFAULT 'local' COMMENT 'local/oss/cos',
    `config`     TEXT COMMENT '渠道配置 JSON（含可选 CDN 绑定 cdn_domain/scheme）',
    `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '上传使用的默认渠道',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB COMMENT '存储渠道';
SQL);
        $this->execute("ALTER TABLE `sys_attachment` ADD COLUMN `channel_id` INT UNSIGNED NULL DEFAULT NULL COMMENT '存储渠道' AFTER `ext`");

        // 播种默认本地渠道，存量附件全部挂靠
        $this->execute("INSERT INTO `sys_storage_channel` (`title`, `type`, `config`, `is_default`) VALUES ('本地存储', 'local', '{\"root\":\"public\"}', 1)");
        $this->execute("UPDATE `sys_attachment` SET `channel_id` = (SELECT MIN(`id`) FROM `sys_storage_channel`) WHERE `channel_id` IS NULL");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `sys_attachment` DROP COLUMN `channel_id`");
        $this->execute('DROP TABLE IF EXISTS `sys_storage_channel`');
    }
}
