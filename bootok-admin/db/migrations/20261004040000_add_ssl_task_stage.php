<?php

use Phinx\Migration\AbstractMigration;

/**
 * 签发过程可视化：任务表记录当前阶段与过程日志
 * 状态流：created → running（worker 认领）→ issued / failed
 */
final class AddSslTaskStage extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `sys_ssl_task`
            ADD COLUMN `stage` VARCHAR(30) NULL DEFAULT NULL COMMENT '当前阶段 order/dns/dns_wait/challenge/finalize/done' AFTER `status`,
            ADD COLUMN `progress` MEDIUMTEXT NULL COMMENT '过程日志 JSON 行数组' AFTER `stage`");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `sys_ssl_task` DROP COLUMN `stage`, DROP COLUMN `progress`");
    }
}
