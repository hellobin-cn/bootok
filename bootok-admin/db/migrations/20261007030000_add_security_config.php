<?php

use Phinx\Migration\AbstractMigration;

/**
 * 安全配置：登录图形验证码开关 + 密码策略（默认关闭保持存量行为，公网部署建议开启）
 */
final class AddSecurityConfig extends AbstractMigration
{
    private const ITEMS = [
        // name, title, value, type, sort
        ['login_captcha', '登录图形验证码', '0', 'bool', 85],
        ['password_min_length', '密码最小长度', '6', 'int', 84],
        ['password_strength', '密码须含字母和数字', '0', 'bool', 83],
    ];

    public function up(): void
    {
        foreach (self::ITEMS as [$name, $title, $value, $type, $sort]) {
            $this->execute(
                "INSERT INTO `sys_config` (`name`, `title`, `value`, `type`, `group`, `sort`)
                 SELECT '{$name}', '{$title}', '{$value}', '{$type}', '安全', {$sort}
                 WHERE NOT EXISTS (SELECT 1 FROM `sys_config` WHERE `name` = '{$name}')"
            );
        }
    }

    public function down(): void
    {
        foreach (self::ITEMS as [$name, , ,]) {
            $this->execute("DELETE FROM `sys_config` WHERE `name` = '{$name}'");
        }
    }
}
