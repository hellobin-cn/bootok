<?php

use Phinx\Migration\AbstractMigration;

/**
 * SSL 证书签发任务（ACME DNS-01 全流程状态持久化）
 * 状态流：created → dns_added → (verify 轮询) → issued / failed
 */
final class CreateSslTask extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_ssl_task` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `domains`        TEXT COMMENT '域名 JSON 数组',
    `ca`             VARCHAR(30) NOT NULL DEFAULT 'staging' COMMENT 'staging/production',
    `dns_provider`   VARCHAR(30) NOT NULL DEFAULT 'aliyun',
    `account_key_pem` TEXT COMMENT 'ACME 账号密钥 PEM',
    `cert_key_pem`   TEXT COMMENT '证书私钥 PEM',
    `csr_der`        TEXT COMMENT 'CSR DER（base64）',
    `kid`            VARCHAR(500) DEFAULT NULL COMMENT 'ACME 账号 URL',
    `order_url`      VARCHAR(500) DEFAULT NULL COMMENT '订单 URL',
    `challenges`     TEXT COMMENT 'DNS-01 挑战 JSON',
    `fullchain_pem`  MEDIUMTEXT COMMENT '证书链 PEM',
    `status`         VARCHAR(20) NOT NULL DEFAULT 'created' COMMENT 'created/dns_added/issued/failed',
    `error`          TEXT,
    `expires_at`     DATETIME NULL DEFAULT NULL,
    `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_status` (`status`)
) ENGINE = InnoDB COMMENT 'SSL 证书签发任务';
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sys_ssl_task`');
    }
}
