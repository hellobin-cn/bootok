<?php

namespace app\service;

use app\model\DnsAccount;
use app\model\SslTask;
use app\service\SslDeployService;
use BootOk\Acme\AcmeClient;
use BootOk\Acme\CertificateIssuer;
use BootOk\Acme\Dns\AliyunProvider;
use BootOk\Acme\Dns\DnsProvider;
use support\Log;

/**
 * SSL 续期服务：供动态任务 ssl.renew_check 调度
 * 策略：issued 且剩余有效期 < 30 天的任务自动重签（复用账号密钥与证书密钥）
 */
class SslRenewService
{
    private const RENEW_WINDOW_DAYS = 30;

    /**
     * 按任务回溯 DNS 凭据；历史任务无凭据引用时回退 sys_config 旧配置
     */
    public static function dnsFor(SslTask $task): DnsProvider
    {
        if ($task->dns_account_id) {
            $account = DnsAccount::find($task->dns_account_id);
            if ($account !== null) {
                return $account->provider();
            }
        }
        $key = ConfigService::get('ssl_aliyun_key', '');
        $secret = ConfigService::get('ssl_aliyun_secret', '');
        if ($key === '' || $secret === '') {
            throw new \RuntimeException('任务未关联 DNS 凭据，且 ssl_aliyun_key / ssl_aliyun_secret 未配置');
        }

        return new AliyunProvider([
            'access_key_id'     => $key,
            'access_key_secret' => $secret,
        ]);
    }

    /**
     * 巡检全部已签发任务，到期窗口内的自动重签。
     *
     * @return array{checked: int, renewed: int, failed: int}
     */
    public static function renewDue(): array
    {
        $checked = 0;
        $renewed = 0;
        $failed = 0;

        $tasks = SslTask::where('status', 'issued')->get();
        foreach ($tasks as $row) {
            $checked++;
            if ($row->expires_at === null) {
                continue;
            }
            $expiresAt = strtotime($row->expires_at);
            if ($expiresAt === false || $expiresAt > time() + self::RENEW_WINDOW_DAYS * 86400) {
                continue; // 未到续期窗口
            }

            try {
                $domains = json_decode($row->domains, true) ?: [];
                $caDir = $row->ca === 'production' ? AcmeClient::CA_LETS_ENCRYPT : AcmeClient::CA_LETS_ENCRYPT_STAGING;
                $issuer = new CertificateIssuer(
                    caDirectory:   $caDir,
                    accountKeyPem: $row->account_key_pem,
                    email:         null,
                    dns:           self::dnsFor($row),
                );
                $result = $issuer->issue($domains, $row->cert_key_pem);

                $row->fullchain_pem = $result['cert'];
                $row->status = 'issued';
                $parsed = openssl_x509_parse($result['cert']);
                $row->expires_at = isset($parsed['validTo_time_t']) ? date('Y-m-d H:i:s', $parsed['validTo_time_t']) : null;
                $row->save();
                $renewed++;
                // 续期成功后同样分发部署（逐目标容错，内部不抛出）
                SslDeployService::dispatchForTask($row);
                Log::channel('json')->info('ssl.renewed', ['id' => $row->id, 'expires_at' => $row->expires_at]);
            } catch (\Throwable $e) {
                $failed++;
                Log::error('ssl.renew_failed', ['id' => $row->id, 'error' => $e->getMessage()]);
            }
        }

        return ['checked' => $checked, 'renewed' => $renewed, 'failed' => $failed];
    }
}
