<?php

namespace app\process;

use app\model\DnsAccount;
use app\model\SslTask;
use app\service\SslDeployService;
use BootOk\Acme\AcmeClient;
use BootOk\Acme\CertificateIssuer;
use support\Log;
use Workerman\Timer;

/**
 * SSL 签发后台进程（单例）：消费待签发任务，让下单接口即时返回
 *
 * 认领语义：status created → running（条件更新，多实例部署也不会重复签发）。
 * 签发为阻塞式（60-120 秒），期间定时器不触发，本进程天然串行。
 */
class SslWorker
{
    public function onWorkerStart(): void
    {
        Timer::add(2, function (): void {
            $this->consume();
        });
    }

    private function consume(): void
    {
        $task = SslTask::where('status', 'created')->orderBy('id')->first();
        if ($task === null) {
            return;
        }
        // 条件更新认领：affected 为 0 说明已被其他实例认领
        $claimed = SslTask::where('id', $task->id)->where('status', 'created')->update(['status' => 'running']);
        if (!$claimed) {
            return;
        }
        $task = SslTask::find($task->id);

        try {
            $this->issue($task);
        } catch (\Throwable $e) {
            $task->status = 'failed';
            $task->error = mb_substr($e->getMessage(), 0, 500);
            $task->save();
            $this->logStage($task, 'failed', '签发失败：' . $e->getMessage());
            Log::error('ssl.issue_failed', ['id' => $task->id, 'error' => $e->getMessage()]);
        }
    }

    private function issue(SslTask $task): void
    {
        $account = DnsAccount::find($task->dns_account_id);
        if ($account === null) {
            throw new \RuntimeException('任务关联的 DNS 凭据不存在（可能已被删除）');
        }
        $caDir = $task->ca === 'production' ? AcmeClient::CA_LETS_ENCRYPT : AcmeClient::CA_LETS_ENCRYPT_STAGING;
        $domains = json_decode((string) $task->domains, true) ?: [];
        $issuer = new CertificateIssuer(
            caDirectory:   $caDir,
            accountKeyPem: (string) $task->account_key_pem,
            email:         'admin@haoduoya.cc',
            dns:           $account->provider(),
        );

        $this->logStage($task, 'order', '开始签发：' . implode('、', $domains));
        $result = $issuer->issue($domains, (string) $task->cert_key_pem, function (string $stage, string $message) use ($task): void {
            $this->logStage($task, $stage, $message);
        });

        $task->fullchain_pem = $result['cert'];
        $task->status = 'issued';
        $task->stage = 'done';
        $parsed = openssl_x509_parse($result['cert']);
        $task->expires_at = isset($parsed['validTo_time_t']) ? date('Y-m-d H:i:s', $parsed['validTo_time_t']) : null;
        $task->save();

        // 签发成功即分发部署（逐目标容错），结果写入过程日志
        $deploy = SslDeployService::dispatchForTask($task);
        $this->logStage(
            $task,
            'deploy',
            $deploy['matched'] > 0
                ? "部署分发：{$deploy['matched']} 个目标，成功 {$deploy['success']}，失败 {$deploy['failed']}"
                : '无绑定的部署目标，跳过分发'
        );
        Log::channel('json')->info('ssl.issued', ['id' => $task->id, 'expires_at' => $task->expires_at, 'deploy' => $deploy]);
    }

    private function logStage(SslTask $task, string $stage, string $message): void
    {
        // 排队期间任务可能已被更新，以库内最新 progress 为基追加
        $fresh = SslTask::find($task->id);
        $log = json_decode((string) ($fresh->progress ?? ''), true) ?: [];
        $log[] = ['time' => date('H:i:s'), 'stage' => $stage, 'message' => $message];
        SslTask::where('id', $task->id)->update([
            'stage'    => $stage,
            'progress' => json_encode($log, JSON_UNESCAPED_UNICODE),
        ]);
        $task->stage = $stage;
        $task->progress = json_encode($log, JSON_UNESCAPED_UNICODE);
    }
}
