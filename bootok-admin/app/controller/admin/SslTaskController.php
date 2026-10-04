<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\model\DnsAccount;
use app\model\SslTask;
use BootOk\Acme\Signer;
use support\Request;
use support\Response;

/**
 * SSL 证书签发操作端点
 *
 * 下单即返回：校验落库待签发任务后立即响应，签发由 ssl 后台进程异步执行
 * （下单 → 加 TXT → DNS 传播 → 挑战验证 → finalize，通常 60-120 秒），
 * 过程通过任务 stage/progress 字段轮询呈现，前端不再长阻塞。
 */
class SslTaskController extends Controller
{
    /**
     * 下单：异步签发的入队动作。
     * POST /ssl/order {domains: "a.com,*.a.com", ca: staging|production, dns_account_id: 1}
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
        $account = DnsAccount::find((int) $request->post('dns_account_id', 0));
        if ($account === null) {
            return $this->error('请选择 DNS 凭据（可在页面下方维护）', '4005');
        }

        $row = SslTask::create([
            'domains'         => json_encode($domains, JSON_UNESCAPED_UNICODE),
            'ca'              => $ca,
            'dns_provider'    => $account->provider,
            'dns_account_id'  => $account->id,
            'account_key_pem' => Signer::generateKey(),
            'cert_key_pem'    => Signer::generateKey(),
            'status'          => 'created',
        ]);

        return $this->success(['id' => $row->id, 'status' => 'created']);
    }

    /**
     * 证书产物下载（type: fullchain / privkey）。
     * GET /ssl/download/{id}/{type}
     */
    public function download(Request $request, int $id, string $type = 'fullchain'): Response
    {
        $row = SslTask::find($id);
        if ($row === null || $row->status !== 'issued') {
            return $this->error('证书未签发', '4004');
        }
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
