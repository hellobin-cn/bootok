<?php

use Phinx\Migration\AbstractMigration;

/**
 * SSL 部署目标：证书签发/续期成功后，把产物投递到目标位置并触发 reload
 *
 * 通道（channel）目前为 ssh，内部按 mode 分两种：
 *   local  —— 证书与本机同机：直接落盘 + 执行 reload 命令，零凭据
 *   remote —— 其他机器：SSH 免密登录（私钥认证），SFTP 上传 + 执行 reload 命令
 *
 * 绑定粒度为主域名：任何一次签发/续期只要覆盖该域名（含通配符形态）即触发部署
 */
final class CreateSslDeploy extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_ssl_deploy` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`            VARCHAR(50) NOT NULL COMMENT '目标名称',
    `domain`           VARCHAR(255) NOT NULL COMMENT '绑定主域名（覆盖即触发）',
    `channel`          VARCHAR(20) NOT NULL DEFAULT 'ssh' COMMENT '部署通道：ssh（webhook 预留）',
    `mode`             VARCHAR(20) NOT NULL DEFAULT 'local' COMMENT 'local=本机落盘 / remote=SSH 免密',
    `config`           TEXT COMMENT '通道配置 JSON',
    `enabled`          TINYINT(1) NOT NULL DEFAULT 1,
    `last_status`      VARCHAR(20) DEFAULT NULL COMMENT '最近部署结果 success/failed',
    `last_deployed_at` DATETIME DEFAULT NULL,
    `last_error`       TEXT,
    `created_at`       DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_domain` (`domain`)
) ENGINE = InnoDB COMMENT 'SSL 部署目标';
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sys_ssl_deploy`');
    }
}
