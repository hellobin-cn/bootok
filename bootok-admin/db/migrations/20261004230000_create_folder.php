<?php

use Phinx\Migration\AbstractMigration;

/**
 * 文件管理器：目录树（与渠道内真实前缀完全对应）+ 附件归属目录
 *
 * 目录按渠道隔离：本地渠道的树映射磁盘目录，OSS/COS 渠道的树映射 bucket 前缀。
 * 空目录由驱动写 .keep 占位对象保证「完全对应」。
 */
final class CreateFolder extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_folder` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel_id` INT UNSIGNED NOT NULL COMMENT '所属存储渠道',
    `parent_id`  INT UNSIGNED NULL DEFAULT NULL COMMENT '父目录，NULL 为渠道根',
    `name`       VARCHAR(255) NOT NULL COMMENT '目录名',
    `path`       VARCHAR(1000) NOT NULL COMMENT '相对渠道根的全路径（不含前导斜杠）',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_channel_path` (`channel_id`, `path`(255)),
    KEY `idx_parent` (`parent_id`)
) ENGINE = InnoDB COMMENT '存储目录（与渠道内真实前缀对应）';
SQL);
        $this->execute("ALTER TABLE `sys_attachment` ADD COLUMN `folder_id` INT UNSIGNED NULL DEFAULT NULL COMMENT '所属目录' AFTER `channel_id`");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `sys_attachment` DROP COLUMN `folder_id`");
        $this->execute('DROP TABLE IF EXISTS `sys_folder`');
    }
}
