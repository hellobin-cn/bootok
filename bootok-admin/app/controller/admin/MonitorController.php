<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\enum\Code;
use app\service\queue\QueueService;
use support\Db;
use support\Redis;
use support\Response;
use Throwable;

/**
 * 系统监控：/health 免认证探针 + /monitor/system 全景（仅超管）
 * 全部字段 best-effort，单项异常不拖垮整个响应
 */
class MonitorController extends Controller
{
    /**
     * GET /health：status=ok 全部健康，degraded 有关键项异常（HTTP 503 供 LB 摘除）
     */
    public function health(): Response
    {
        $checks = [
            'db'      => $this->try(fn() => Db::select('SELECT 1') !== null),
            'redis'   => $this->try(fn() => Redis::connection()->ping() === true || Redis::connection()->ping() === '+PONG' || is_string(Redis::connection()->ping())),
        ];
        $queueHeartbeat = (int) (Redis::connection()->get('bootok:queue:heartbeat') ?: 0);
        $crontabHeartbeat = (int) (Redis::connection()->get('crontab:heartbeat') ?: 0);
        $checks['queue_worker'] = $queueHeartbeat > time() - 120;
        $checks['crontab'] = $crontabHeartbeat > time() - 120;
        $ok = $checks['db'] && $checks['redis'];
        $status = $ok ? 'ok' : 'degraded';
        return json(['status' => $status, 'checks' => $checks, 'time' => date('Y-m-d H:i:s')])
            ->withStatus($ok ? 200 : 503);
    }

    /**
     * GET /monitor/system：服务器/PHP/MySQL/Redis/队列/任务 全景
     */
    public function stats(): Response
    {
        if (!$this->request->getCustomData('isSuper', 0)) {
            return $this->error('仅超级管理员可查看系统监控', Code::FORBIDDEN->value);
        }
        return $this->success([
            'server' => $this->serverInfo(),
            'php'    => $this->phpInfo(),
            'mysql'  => $this->mysqlInfo(),
            'redis'  => $this->redisInfo(),
            'queue'  => $this->try(fn() => (new QueueService())->stats()) ?: null,
            'crontab_heartbeat' => (int) (Redis::connection()->get('crontab:heartbeat') ?: 0),
            'time'   => date('Y-m-d H:i:s'),
        ]);
    }

    private function serverInfo(): array
    {
        $base = base_path();
        [$memTotal, $memFree] = $this->systemMemory();
        return [
            'os'          => php_uname('s') . ' ' . php_uname('r'),
            'mem_total'   => $memTotal,
            'mem_free'    => $memFree,
            'load'        => function_exists('sys_getloadavg') ? sys_getloadavg() : null,
            'disk_total'  => @disk_total_space($base) ?: 0,
            'disk_free'   => @disk_free_space($base) ?: 0,
        ];
    }

    private function phpInfo(): array
    {
        return [
            'version'      => PHP_VERSION,
            'sapi'         => php_sapi_name(),
            'process_mem'  => memory_get_usage(true),
            'peak_mem'     => memory_get_peak_usage(true),
            'memory_limit' => ini_get('memory_limit'),
            'opcache'      => function_exists('opcache_get_status') ? (bool) @opcache_get_status(false) : false,
        ];
    }

    private function mysqlInfo(): array
    {
        return $this->try(function () {
            $version = Db::select('SELECT VERSION() as v')[0]->v ?? '';
            $size = Db::select("SELECT COALESCE(SUM(data_length + index_length),0) s, COUNT(*) c FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE()")[0] ?? null;
            $threads = Db::select("SHOW STATUS LIKE 'Threads_connected'")[0]->Value ?? '';
            return [
                'version'     => (string) $version,
                'data_size'   => (int) ($size->s ?? 0),
                'tables'      => (int) ($size->c ?? 0),
                'connections' => (int) $threads,
            ];
        }) ?: null;
    }

    private function redisInfo(): array
    {
        return $this->try(function () {
            $redis = Redis::connection();
            $info = $redis->info('memory') ?: [];
            return [
                'used_memory'   => (int) ($info['used_memory'] ?? 0),
                'keys'          => (int) $redis->dbsize(),
                'connected'     => true,
            ];
        }) ?: null;
    }

    /**
     * 系统内存（字节）：Linux 读 /proc/meminfo，Windows 走 WMI，取不到为 null
     */
    private function systemMemory(): array
    {
        $linux = @file_get_contents('/proc/meminfo');
        if ($linux !== false && preg_match('/MemTotal:\s+(\d+) kB.*?MemFree:\s+(\d+) kB/s', $linux, $m)) {
            return [(int) $m[1] * 1024, (int) $m[2] * 1024];
        }
        try {
            $wmi = new \COM('WinMgmts:\\\\.\\root\\cimv2');
            foreach ($wmi->ExecQuery('SELECT TotalVisibleMemorySize, FreePhysicalMemory FROM Win32_OperatingSystem') as $os) {
                return [(int) $os->TotalVisibleMemorySize * 1024, (int) $os->FreePhysicalMemory * 1024];
            }
        } catch (Throwable) {
        }
        return [null, null];
    }

    /** best-effort：异常吞掉返回 null/false，单项故障不影响整体响应 */
    private function try(callable $fn): mixed
    {
        try {
            return $fn();
        } catch (Throwable) {
            return false;
        }
    }
}
