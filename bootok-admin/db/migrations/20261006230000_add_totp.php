<?php

use Phinx\Migration\AbstractMigration;

/**
 * 两步验证（TOTP）：管理员绑定密钥与启用标志
 * totp_secret 泄露等价于密码泄露，模型层列入 hidden，永不外发
 */
final class AddTotp extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `sys_admin`
            ADD COLUMN `totp_secret` VARCHAR(64) NOT NULL DEFAULT '' COMMENT 'TOTP 密钥（base32）' AFTER `admin_password`,
            ADD COLUMN `totp_enabled` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '两步验证启用' AFTER `totp_secret`");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `sys_admin` DROP COLUMN `totp_secret`, DROP COLUMN `totp_enabled`");
    }
}
