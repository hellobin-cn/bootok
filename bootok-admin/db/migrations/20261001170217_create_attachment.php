<?php

use Phinx\Migration\AbstractMigration;

/**
 * 统一附件表：上传即落库（数据资产化，为引用计数/清理预留）
 */
final class CreateAttachment extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_attachment` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`      INT UNSIGNED NOT NULL DEFAULT 0,
    `admin_name`    VARCHAR(50)  NOT NULL DEFAULT '',
    `path`          VARCHAR(500) NOT NULL COMMENT '存储路径/URL',
    `original_name` VARCHAR(255) NOT NULL DEFAULT '' COMMENT '原始文件名',
    `size`          INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '字节',
    `ext`           VARCHAR(10)  NOT NULL DEFAULT '',
    `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_id` (`admin_id`)
) ENGINE = InnoDB COMMENT '附件';
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sys_attachment`');
    }
}
