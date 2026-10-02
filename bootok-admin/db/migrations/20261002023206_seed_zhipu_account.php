<?php

use Phinx\Migration\AbstractMigration;

/**
 * 种子：zhipu 验证专用账号（AI 测试用，超管；密码 bcrypt）
 */
final class SeedZhipuAccount extends AbstractMigration
{
    public function up(): void
    {
        if ($this->fetchRow("SELECT COUNT(*) AS c FROM `sys_admin` WHERE `admin_name` = 'zhipu'")['c'] > 0) {
            return;
        }
        $this->execute("INSERT INTO `sys_admin` (`admin_name`, `admin_password`, `group_id`, `is_super`) VALUES ('zhipu', '$2y$12$3zSet1uIVeaeeM7QEsrrSuos6a3obYzTKJcJsAPCNGFjIjgM15wbe', 1, 1)");
    }

    public function down(): void
    {
        $this->execute("DELETE FROM `sys_admin` WHERE `admin_name` = 'zhipu'");
    }
}
