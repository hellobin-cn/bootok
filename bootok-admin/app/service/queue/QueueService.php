<?php

namespace app\service\queue;

use app\process\QueueRegistry;
use support\Redis;
use Throwable;

/**
 * 队列服务：异步任务的统一入口（Redis list 即时 + zset 延迟）
 *
 * 数据结构（键前缀 bootok:queue:）：
 *   pending  list   即时任务，lPush 入 / rPop 出（FIFO）
 *   delayed  zset   延迟任务，score=到期时间戳，worker 到期搬入 pending
 *   failed   list   死信（重试耗尽），上限 200 条，可整体重排
 * Job 报文：{id, action, args, attempts}
 */
class QueueService
{
    private const PREFIX = 'bootok:queue:';
    public const MAX_ATTEMPTS = 3;
    /** 重试退避基数（秒），第 n 次重试延迟 n*BASE */
    public const RETRY_BASE_SECONDS = 5;
    public const FAILED_CAP = 200;

    /**
     * 投递任务
     *
     * @param string $action QueueRegistry 登记的动作键
     * @param array  $args   处理器参数
     * @param int    $delay  延迟秒数，0 为立即执行
     * @return string 任务 id
     * @throws Throwable 动作未登记或 redis 异常
     */
    public function dispatch(string $action, array $args = [], int $delay = 0): string
    {
        if (!QueueRegistry::has($action)) {
            throw new \RuntimeException("队列动作未登记: {$action}");
        }
        $job = json_encode([
            'id'       => uniqid('q', true),
            'action'   => $action,
            'args'     => $args,
            'attempts' => 0,
        ], JSON_UNESCAPED_UNICODE);
        if ($delay > 0) {
            Redis::connection()->zAdd(self::PREFIX . 'delayed', time() + $delay, (string) $job);
        } else {
            Redis::connection()->lPush(self::PREFIX . 'pending', (string) $job);
        }
        return (string) $job;
    }

    /**
     * 队列概况（worker 心跳/三队列长度）
     */
    public function stats(): array
    {
        $conn = Redis::connection();

        return [
            'pending'   => (int) $conn->lLen(self::PREFIX . 'pending'),
            'delayed'   => (int) $conn->zCard(self::PREFIX . 'delayed'),
            'failed'    => (int) $conn->lLen(self::PREFIX . 'failed'),
            'heartbeat' => (int) $conn->get(self::PREFIX . 'heartbeat'),
        ];
    }

    /**
     * 死信列表（新的在前）
     */
    public function failedJobs(int $limit = 50): array
    {
        $raw = Redis::connection()->lRange(self::PREFIX . 'failed', 0, max(0, $limit - 1)) ?: [];
        return array_values(array_map(fn($line) => json_decode((string) $line, true) ?: ['raw' => $line], $raw));
    }

    /**
     * 死信全部重排回 pending（不重置 attempts——已重试耗尽的任务重排后仅再给一轮）
     */
    public function retryFailed(): int
    {
        $conn = Redis::connection();
        $count = 0;
        while ($job = $conn->rPop(self::PREFIX . 'failed')) {
            $conn->lPush(self::PREFIX . 'pending', (string) $job);
            $count++;
        }
        return $count;
    }
}
