<?php

use Phinx\Migration\AbstractMigration;

/**
 * DNS 凭据：DNS 服务商的鉴权账号，签发/续期证书时选用
 *
 * 迁移同时：
 * 1. sys_ssl_task 增加 dns_account_id（任务记住所用凭据，续期按任务回溯）
 * 2. 用 sys_config 中的旧版 ssl_aliyun_key/ssl_aliyun_secret 播种一条默认凭据，
 *    并把存量任务挂到该凭据上，保证历史任务续期不断链
 */
final class CreateDnsAccount extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_dns_account` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(50) NOT NULL COMMENT '凭据名称',
    `provider`   VARCHAR(20) NOT NULL DEFAULT 'aliyun' COMMENT 'aliyun/dnspod',
    `config`     TEXT COMMENT '凭据 JSON',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB COMMENT 'DNS 凭据';
SQL);

        $this->execute("ALTER TABLE `sys_ssl_task` ADD COLUMN `dns_account_id` INT UNSIGNED NULL DEFAULT NULL COMMENT 'DNS 凭据' AFTER `dns_provider`");

        // 旧配置播种为默认凭据（幂等），存量任务挂靠
        $this->execute(<<<'SQL'
INSERT INTO `sys_dns_account` (`title`, `provider`, `config`)
SELECT '阿里云（默认）', 'aliyun',
       CONCAT('{"access_key_id":"', (SELECT `value` FROM `sys_config` WHERE `name` = 'ssl_aliyun_key'),
              '","access_key_secret":"', (SELECT `value` FROM `sys_config` WHERE `name` = 'ssl_aliyun_secret'), '"}')
WHERE NOT EXISTS (SELECT 1 FROM `sys_dns_account`)
SQL);
        $this->execute("UPDATE `sys_ssl_task` SET `dns_account_id` = (SELECT MIN(`id`) FROM `sys_dns_account`) WHERE `dns_account_id` IS NULL");
    }

    public function down(): void
    {
        $this->execute('ALTER TABLE `sys_ssl_task` DROP COLUMN IF EXISTS `dns_account_id`');
        $this->execute('DROP TABLE IF EXISTS `sys_dns_account`');
    }
}
