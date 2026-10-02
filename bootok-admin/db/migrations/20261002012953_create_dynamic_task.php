<?php

use Phinx\Migration\AbstractMigration;

/**
 * 动态计划任务（后台管理，DB定义）
 * 任务定义存表：name/cron/action/status；执行链路复用 sys_task_run
 * action 为登记在 TaskRegistry::dynamic() 中的动作键（后台编排现有能力，绝不 eval 用户输入）
 */
final class CreateDynamicTask extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_task` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(50)  NOT NULL COMMENT '任务名（唯一）',
    `cron`       VARCHAR(30)  NOT NULL DEFAULT '* * * * *' COMMENT '五段cron表达式',
    `action`     VARCHAR(50)  NOT NULL COMMENT '动作键（TaskRegistry::dynamic 登记）',
    `remark`     VARCHAR(255) NOT NULL DEFAULT '' COMMENT '备注',
    `status`     TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1启用 0停用',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_name` (`name`)
) ENGINE = InnoDB COMMENT '动态计划任务';

-- 种子：一个演示任务（每5分钟执行系统健康巡检动作）
INSERT INTO `sys_task` (`name`, `cron`, `action`, `remark`, `status`) VALUES
('后台创建的演示任务', '*/5 * * * *', 'system.health_check', '演示：后台创建的计划任务，每5分钟执行健康巡检', 1);
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sys_task`');
    }
}
