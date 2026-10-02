<?php

use Phinx\Migration\AbstractMigration;

/**
 * 公告（管理员站内公告，底座自带模块）
 */
final class CreateNotice extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `notice` (
    `notice_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(100) NOT NULL COMMENT '标题',
    `content`    MEDIUMTEXT COMMENT '内容',
    `status`     TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1发布 0下线',
    `sort`       INT NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`notice_id`)
) ENGINE = InnoDB COMMENT '公告';
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `notice`');
    }
}
