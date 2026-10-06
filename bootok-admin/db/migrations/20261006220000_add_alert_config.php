<?php

use Phinx\Migration\AbstractMigration;

/**
 * IM 告警配置项：钉钉/企业微信/飞书群机器人 webhook（运营可调项，走配置中心）
 */
final class AddAlertConfig extends AbstractMigration
{
    private const ITEMS = [
        // name, title, value, sort
        ['alert_im_type', 'IM 告警通道类型', 'none', 90],
        ['alert_im_webhook', 'IM 告警 Webhook 地址', '', 89],
        ['alert_im_secret', 'IM 告警加签密钥（钉钉/飞书需要）', '', 88],
    ];

    public function up(): void
    {
        foreach (self::ITEMS as [$name, $title, $value, $sort]) {
            $this->execute(
                "INSERT INTO `sys_config` (`name`, `title`, `value`, `type`, `group`, `sort`)
                 SELECT '{$name}', '{$title}', '{$value}', 'text', '告警', {$sort}
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
