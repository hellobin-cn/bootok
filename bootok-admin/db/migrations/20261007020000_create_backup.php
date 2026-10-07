<?php

use Phinx\Migration\AbstractMigration;

/**
 * 数据库备份记录：备份文件推存储渠道，本表存元数据；保留策略按成功条数清理
 */
final class CreateBackup extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_backup` (
    `backup_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `filename`    VARCHAR(255) NOT NULL COMMENT '备份文件名',
    `object`      VARCHAR(255) NOT NULL DEFAULT '' COMMENT '存储渠道对象路径（成功才有）',
    `size`        BIGINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '压缩包字节',
    `tables`      INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '表数量',
    `trigger`     VARCHAR(10) NOT NULL DEFAULT 'manual' COMMENT 'task定时/manual手动',
    `status`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0失败/1成功',
    `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
    `error`       VARCHAR(500) NOT NULL DEFAULT '',
    `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`backup_id`)
) ENGINE = InnoDB COMMENT '数据库备份';

INSERT INTO `sys_config` (`name`, `title`, `value`, `type`, `group`, `sort`)
SELECT 'backup_keep', '备份保留份数', '7', 'int', '备份', 95
WHERE NOT EXISTS (SELECT 1 FROM `sys_config` WHERE `name` = 'backup_keep');
SQL);
    }

    public function down(): void
    {
        $this->execute("DELETE FROM `sys_config` WHERE `name` = 'backup_keep'");
        $this->execute('DROP TABLE IF EXISTS `sys_backup`');
    }
}
