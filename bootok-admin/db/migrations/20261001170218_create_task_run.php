<?php

use Phinx\Migration\AbstractMigration;

/**
 * 计划任务执行记录
 */
final class CreateTaskRun extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_task_run` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`        VARCHAR(50)  NOT NULL COMMENT '任务名',
    `trigger`     VARCHAR(10)  NOT NULL DEFAULT 'cron' COMMENT 'cron/schedule/manual',
    `status`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '1成功 0失败 2执行中',
    `duration_ms` INT UNSIGNED NOT NULL DEFAULT 0,
    `error`       TEXT,
    `created_at`  DATETIME DEFAULT CURRENT_TIMESTAMP,
    `finished_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_name` (`name`)
) ENGINE = InnoDB COMMENT '任务执行记录';
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sys_task_run`');
    }
}
