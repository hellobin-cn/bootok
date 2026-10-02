<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\model\SslTask;
use app\service\ConfigService;
use BootOk\Acme\AcmeClient;
use BootOk\Acme\CertificateIssuer;
use BootOk\Acme\Dns\AliyunProvider;
use BootOk\Acme\Dns\DnsProvider;
use BootOk\Acme\Signer;
use support\Request;
use support\Response;

/**
 * SSL 证书签发可视化操作端点
 *
 * webman 常驻进程无 FPM 超时限制：下单请求同步阻塞完成
 * 下单 → 阿里云加 TXT → DNS 传播探测 → 挑战验证 → finalize（通常 60-90 秒），
 * 前端以转圈等待 + 轮询任务状态呈现。
 */
class SslTaskController extends Controller
{
    private const CFG_ALIYUN_KEY = 'ssl_aliyun_key';
    private const CFG_ALIYUN_SECRET = 'ssl_aliyun_secret';

    private function dns(): DnsProvider
    {
        $key = ConfigService::get(self::CFG_ALIYUN_KEY, '');
        $secret = ConfigService::get(self::CFG_ALIYUN_SECRET, '');
        if ($key === '' || $secret === '') {
            throw new \RuntimeException('请先在系统配置中维护 ssl_aliyun_key / ssl_aliyun_secret');
        }

        return new AliyunProvider([
            'access_key_id'     => $key,
            'access_key_secret' => $secret,
        ]);
    }

    private function issuer(string $accountKeyPem, string $ca, ?string $email = null): CertificateIssuer
    {
        $caDir = $ca === 'production' ? AcmeClient::CA_LETS_ENCRYPT : AcmeClient::CA_LETS_ENCRYPT_STAGING;

        return new CertificateIssuer(
            caDirectory:   $caDir,
            accountKeyPem: $accountKey,
            email:         $email,
            dns:           $this->dns(),
        );
    }

    /**
     * 一站式签发：同步阻塞（60-120 秒），前端转圈等待。
     * POST /ssl/order {domains: "a.com,*.a.com", ca: staging|production}
     */
    public function order(Request $request): Response
    {
        $raw = (string) $request->post('domains', '');
        $ca = (string) $request->post('ca', 'staging') === 'production' ? 'production' : 'staging';
        $domains = array_values(array_filter(array_map('trim', explode(',', $raw))));
        if ($domains === []) {
            return $this->error('请填写域名', '4005');
        }
        foreach ($domains as $domain) {
            if (!preg_match('/^(\*\.)?[a-z0-9][a-z0-9\.\-]*[a-z0-9]$/', $domain)) {
                return $this->error('域名格式错误：' . $domain, '4005');
            }
        }

        $accountKey = Signer::generateKey();
        $certKey = Signer::generateKey();

        $row = SslTask::create([
            'domains'         => json_encode($domains, JSON_UNESCAPED_UNICODE),
            'ca'              => $ca,
            'dns_provider'    => 'aliyun',
            'account_key_pem' => $accountKey,
            'cert_key_pem'    => $certKey,
            'status'          => 'created',
        ]);

        try {
            $result = $this->issuer($accountKey, $ca, 'admin@haoduoya.cc')->issue($domains, $certKey);

            $row->fullchain_pem = $result['cert'];
            $row->status = 'issued';
            $parsed = openssl_x509_parse($result['cert']);
            $row->expires_at = isset($parsed['validTo_time_t']) ? date('Y-m-d H:i:s', $parsed['validTo_time_t']) : null;
            $row->save();

            return $this->success([
                'id'         => $row->id,
                'status'     => 'issued',
                'domains'    => $domains,
                'expires_at' => $row->expires_at,
            ]);
        } catch (\Throwable $e) {
            $row->status = 'failed';
            $row->error = mb_substr($e->getMessage(), 0, 500);
            $row->save();

            return $this->error('签发失败：' . $e->getMessage());
        }
    }

    /**
     * 证书产物下载（type: fullchain / privkey）。
     * GET /ssl/download/{id}/{type}
     */
    public function download(Request $request): Response
    {
        $row = SslTask::find((int) $request->param('id', 0));
        if ($row === null || $row->status !== 'issued') {
            return $this->error('证书未签发', '4004');
        }
        $type = (string) $request->route('type', 'fullchain');
        $content = $type === 'privkey' ? $row->cert_key_pem : $row->fullchain_pem;
        if ($content === null || $content === '') {
            return $this->error('产物不存在', '4004');
        }
        $domains = json_decode($row->domains, true) ?: ['ssl'];
        $filename = ($row->ca === 'production' ? '' : 'staging.') . implode('.', $domains) . ".{$type}.pem";

        return response($content)->withHeaders([
            'Content-Type'        => 'application/x-pem-file',
            'Content-Disposition' => "attachment; filename*=UTF-8''" . rawurlencode($filename),
        ]);
    }
}
