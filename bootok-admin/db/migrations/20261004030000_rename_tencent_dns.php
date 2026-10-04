<?php

use Phinx\Migration\AbstractMigration;

/**
 * DNS 凭据命名与凭据格式修正：
 * 1. 供应商标识 dnspod → tencent（凭据是腾讯云 CAM 的，DNSPod 只是产品名，命名应明朗）
 * 2. 早期版本把腾讯云 SecretId/SecretKey 拼进 login_token（旧版 DNSPod API 形态），
 *    实测旧版 API 不认腾讯云密钥（HTTP 401），统一拆分为 secret_id/secret_key 走 API 3.0
 */
final class RenameTencentDns extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("UPDATE `sys_dns_account` SET `provider` = 'tencent' WHERE `provider` = 'dnspod'");
        $this->execute(<<<'SQL'
UPDATE `sys_dns_account`
SET `config` = JSON_OBJECT(
    'secret_id', SUBSTRING_INDEX(JSON_UNQUOTE(JSON_EXTRACT(`config`, '$.login_token')), ',', 1),
    'secret_key', SUBSTRING_INDEX(JSON_UNQUOTE(JSON_EXTRACT(`config`, '$.login_token')), ',', -1)
)
WHERE `provider` = 'tencent'
  AND JSON_EXTRACT(`config`, '$.login_token') IS NOT NULL
SQL);
    }

    public function down(): void
    {
        // 数据形态转换不做回滚（down 仅还原标识）
        $this->execute("UPDATE `sys_dns_account` SET `provider` = 'dnspod' WHERE `provider` = 'tencent'");
    }
}
