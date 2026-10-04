<?php

namespace app\service;

use app\cloud\CdnCertSynchronizer;
use app\model\SslDeploy;
use app\model\SslTask;
use phpseclib3\Crypt\PublicKeyLoader;
use phpseclib3\Net\SFTP;
use phpseclib3\Net\SSH2;
use support\Log;

/**
 * SSL 部署服务：把签发/续期成功的证书投递到部署目标
 *
 * 绑定粒度为主域名：任务的域名集合覆盖目标 domain（含通配符形态）即触发。
 * 部署失败不影响签发结果——错误记录在目标行（last_status/last_error），可手动重试。
 */
class SslDeployService
{
    /**
     * 签发/续期成功后分发：对所有启用且域名匹配的目标执行部署（逐个容错，不抛出）
     *
     * @return array{matched: int, success: int, failed: int}
     */
    public static function dispatchForTask(SslTask $task): array
    {
        $domains = json_decode((string) $task->domains, true) ?: [];
        $matched = 0;
        $success = 0;
        $failed = 0;

        if ($domains === []) {
            return compact('matched', 'success', 'failed');
        }
        $targets = SslDeploy::where('enabled', true)->get();
        foreach ($targets as $target) {
            if (!self::domainMatched($domains, $target->domain)) {
                continue;
            }
            $matched++;
            try {
                self::execute($target, (string) $task->fullchain_pem, (string) $task->cert_key_pem);
                self::record($target, 'success');
                $success++;
            } catch (\Throwable $e) {
                self::record($target, 'failed', $e->getMessage());
                $failed++;
                Log::error('ssl.deploy_failed', ['deploy_id' => $target->id, 'task_id' => $task->id, 'error' => $e->getMessage()]);
            }
        }

        return compact('matched', 'success', 'failed');
    }

    /**
     * 立即部署：对目标绑定域名下最近一次签发成功的任务重新投递
     */
    public static function runNow(SslDeploy $target): void
    {
        $task = self::latestTaskFor($target->domain);
        if ($task === null) {
            throw new \RuntimeException('该域名下暂无已签发的证书任务');
        }
        self::execute($target, (string) $task->fullchain_pem, (string) $task->cert_key_pem);
        self::record($target, 'success');
    }

    /**
     * 连通性测试（不执行 reload/推送）：
     * ssh+local —— 校验落盘目录可创建/可写
     * ssh+remote —— SSH 登录并执行 echo
     * cdn —— 只读查询云 CDN，验证凭据与域名归属
     *
     * @return array{ok: bool, message: string}
     */
    public static function test(SslDeploy $target): array
    {
        $config = $target->config ?? [];
        if ($target->channel === 'cdn') {
            return CdnCertSynchronizer::probe((string) $target->mode, $config);
        }
        if ($target->mode === 'remote') {
            $ssh = self::sshSession($config);
            $output = $ssh->exec('echo bootok-deploy-ok');
            $ssh->disconnect();

            return ['ok' => true, 'message' => 'SSH 登录成功：' . trim($output)];
        }
        foreach (['cert_path', 'key_path'] as $field) {
            $dir = dirname((string) $config[$field]);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                return ['ok' => false, 'message' => "目录不可创建：{$dir}"];
            }
            if (!is_writable($dir)) {
                return ['ok' => false, 'message' => "目录不可写：{$dir}"];
            }
        }

        return ['ok' => true, 'message' => '本机目录可写'];
    }

    /**
     * 域名匹配：任务域名与目标主域名相等，或为通配符形态 *.主域名
     *
     * @param string[] $domains
     */
    private static function domainMatched(array $domains, string $domain): bool
    {
        foreach ($domains as $d) {
            if ($d === $domain || $d === '*.' . $domain) {
                return true;
            }
        }

        return false;
    }

    private static function latestTaskFor(string $domain): ?SslTask
    {
        $tasks = SslTask::where('status', 'issued')->orderByDesc('id')->get();
        foreach ($tasks as $task) {
            $domains = json_decode((string) $task->domains, true) ?: [];
            if (self::domainMatched($domains, $domain) && (string) $task->fullchain_pem !== '') {
                return $task;
            }
        }

        return null;
    }

    /**
     * 按模式执行投递：落盘证书 + 执行 reload 命令
     *
     * @param array<string, string> $config
     */
    private static function execute(SslDeploy $target, string $fullchain, string $privkey): void
    {
        $config = $target->config ?? [];

        // 云 CDN 通道：证书推到云 CDN 域名的 HTTPS 配置（无需路径与 reload 概念）
        if ($target->channel === 'cdn') {
            CdnCertSynchronizer::deploy((string) $target->mode, $config, (string) $target->domain, $fullchain, $privkey);

            return;
        }

        $certPath = (string) ($config['cert_path'] ?? '');
        $keyPath = (string) ($config['key_path'] ?? '');
        $reloadCmd = (string) ($config['reload_cmd'] ?? '');

        if ($target->mode === 'remote') {
            $ssh = self::sshSession($config);
            $sftp = new SFTP((string) $config['host'], (int) ($config['port'] ?? 22), 30);
            if (!$sftp->login((string) $config['username'], PublicKeyLoader::load((string) $config['private_key']))) {
                $ssh->disconnect();
                throw new \RuntimeException('SFTP 登录失败');
            }
            if (!$sftp->put($certPath, $fullchain) || !$sftp->put($keyPath, $privkey)) {
                $sftp->disconnect();
                $ssh->disconnect();
                throw new \RuntimeException('SFTP 上传失败：' . $sftp->getLastSFTPError());
            }
            $sftp->disconnect();
            // phpseclib3 的 exec 返回 stdout，exit code 另查；非零退出视为失败
            $output = $ssh->exec($reloadCmd);
            $exit = $ssh->getExitStatus();
            $ssh->disconnect();
            if ($exit !== false && $exit !== 0) {
                throw new \RuntimeException("reload 命令退出码 {$exit}：" . mb_substr(trim($output), 0, 300));
            }

            return;
        }

        // local：直接落盘（私钥尽量收敛权限），再本地执行 reload
        foreach (['cert_path' => $fullchain, 'key_path' => $privkey] as $field => $content) {
            $path = $field === 'cert_path' ? $certPath : $keyPath;
            $dir = dirname($path);
            if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
                throw new \RuntimeException("目录不可创建：{$dir}");
            }
            if (@file_put_contents($path, $content) === false) {
                throw new \RuntimeException("写入失败：{$path}");
            }
            if ($field === 'key_path') {
                @chmod($path, 0600);
            }
        }
        [$exit, $output] = self::runCommand($reloadCmd);
        if ($exit !== 0) {
            throw new \RuntimeException("reload 命令退出码 {$exit}：" . mb_substr(trim($output), 0, 300));
        }
    }

    private static function sshSession(array $config): SSH2
    {
        $ssh = new SSH2((string) $config['host'], (int) ($config['port'] ?? 22), 30);
        if (!$ssh->login((string) $config['username'], PublicKeyLoader::load((string) $config['private_key']))) {
            throw new \RuntimeException('SSH 登录失败：' . ($ssh->getLastError() ?: '认证被拒绝'));
        }

        return $ssh;
    }

    /**
     * 跨平台执行命令并捕获退出码与合并输出
     *
     * @return array{0: int, 1: string}
     */
    private static function runCommand(string $cmd): array
    {
        $pipes = [];
        $process = proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            return [255, 'proc_open 启动失败'];
        }
        $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($process);

        return [(int) $exit, (string) $output];
    }

    private static function record(SslDeploy $target, string $status, string $error = ''): void
    {
        $target->last_status = $status;
        $target->last_deployed_at = date('Y-m-d H:i:s');
        $target->last_error = $error === '' ? null : mb_substr($error, 0, 500);
        $target->save();
    }
}
