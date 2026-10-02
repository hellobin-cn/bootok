<?php

use Phinx\Migration\AbstractMigration;

/**
 * 字段级变更审计：模型声明 public bool $auditChange = true 后，
 * /auto 资源的 create/edit/delete/batchDelete 自动记录字段 diff
 */
final class CreateChangeLog extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_change_log` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `table_name` VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '表名',
    `pk_value`   VARCHAR(64)  NOT NULL DEFAULT '' COMMENT '主键值',
    `action`     VARCHAR(10)  NOT NULL DEFAULT '' COMMENT 'created/updated/deleted',
    `changes`    TEXT COMMENT '字段diff JSON：{field:{from,to}}',
    `admin_id`   INT UNSIGNED NOT NULL DEFAULT 0,
    `admin_name` VARCHAR(50)  NOT NULL DEFAULT '',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_table_pk` (`table_name`, `pk_value`)
) ENGINE = InnoDB COMMENT '字段级变更审计';
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sys_change_log`');
    }
}
