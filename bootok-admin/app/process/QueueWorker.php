<?php

namespace app\process;

use app\service\MessageService;
use app\service\queue\QueueService;
use support\Log;
use support\Redis;
use Throwable;
use Workerman\Timer;

/**
 * 队列消费进程（单例）：到期延迟任务搬运 + pending 消费 + 失败重试/死信
 *
 * 吞吐语义：每 200ms 消费一条，天然串行；重试按 5s*次数 退避，超过
 * MAX_ATTEMPTS 进死信（bootok:queue:failed，上限 200）并站内通知超管。
 */
class QueueWorker
{
    private const PREFIX = 'bootok:queue:';

    public function onWorkerStart(): void
    {
        // 心跳：观测消费进程存活
        Timer::add(30, function (): void {
            Redis::connection()->setex(self::PREFIX . 'heartbeat', 120, (string) time());
        });
        Redis::connection()->setex(self::PREFIX . 'heartbeat', 120, (string) time());

        Timer::add(0.2, function (): void {
            $this->tick();
        });
    }

    private function tick(): void
    {
        $this->migrateDue();
        $job = $this->pop();
        if ($job === null) {
            return;
        }
        $this->execute($job);
    }

    /** 到期的延迟任务搬入 pending（单例消费，无并发争抢） */
    private function migrateDue(): void
    {
        $conn = Redis::connection();
        $due = $conn->zRangeByScore(self::PREFIX . 'delayed', '-inf', (string) time(), ['limit' => [0, 50]]) ?: [];
        foreach ($due as $job) {
            // 先删再入：删除失败（已被搬）时不重复入队
            if ($conn->zRem(self::PREFIX . 'delayed', (string) $job)) {
                $conn->lPush(self::PREFIX . 'pending', (string) $job);
            }
        }
    }

    private function pop(): ?array
    {
        $raw = Redis::connection()->rPop(self::PREFIX . 'pending');
        if ($raw === false || $raw === null || $raw === '') {
            return null;
        }
        $job = json_decode((string) $raw, true);
        return is_array($job) ? $job : null;
    }

    private function execute(array $job): void
    {
        $action = (string) ($job['action'] ?? '');
        $job['attempts'] = (int) ($job['attempts'] ?? 0) + 1;
        $started = microtime(true);
        try {
            QueueRegistry::execute($action, (array) ($job['args'] ?? []));
            Log::channel('json')->info('queue.processed', [
                'id' => $job['id'] ?? '', 'action' => $action,
                'attempt' => $job['attempts'], 'ms' => (int) ((microtime(true) - $started) * 1000),
            ]);
        } catch (Throwable $e) {
            if ($job['attempts'] < QueueService::MAX_ATTEMPTS) {
                $delay = QueueService::RETRY_BASE_SECONDS * $job['attempts'];
                Redis::connection()->zAdd(self::PREFIX . 'delayed', time() + $delay, (string) json_encode($job, JSON_UNESCAPED_UNICODE));
                Log::warning("队列任务第 {$job['attempts']} 次失败，{$delay}s 后重试: {$action} " . $e->getMessage());
                return;
            }
            // 重试耗尽 → 死信 + 通知超管
            $dead = json_encode([
                'job'       => $job,
                'error'     => mb_substr($e->getMessage(), 0, 500),
                'failed_at' => date('Y-m-d H:i:s'),
            ], JSON_UNESCAPED_UNICODE);
            Redis::connection()->lPush(self::PREFIX . 'failed', (string) $dead);
            Redis::connection()->lTrim(self::PREFIX . 'failed', 0, QueueService::FAILED_CAP - 1);
            Log::error("队列任务最终失败: {$action} " . $e->getMessage());
            (new MessageService())->sendToSupers(
                "队列任务最终失败：{$action}",
                $e->getMessage(),
                MessageService::TYPE_TASK
            );
        }
    }
}
