<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\service\queue\QueueService;
use support\Response;

/**
 * 队列观测端点（仅超管）：概况与死信管理
 */
class QueueController extends Controller
{
    public function __construct(private readonly QueueService $queue)
    {
        parent::__construct();
    }

    /**
     * GET /queue/stats：队列长度 + 心跳 + 最近死信
     */
    public function stats(): Response
    {
        if (!$this->request->getCustomData('isSuper', 0)) {
            return $this->error('仅超级管理员可查看队列');
        }
        return $this->success([
            'stats'  => $this->queue->stats(),
            'failed' => $this->queue->failedJobs(50),
        ]);
    }

    /**
     * POST /queue/retry-failed：死信全部重排
     */
    public function retryFailed(): Response
    {
        if (!$this->request->getCustomData('isSuper', 0)) {
            return $this->error('仅超级管理员可操作队列');
        }
        $count = $this->queue->retryFailed();
        return $this->success(['retry' => $count], "已重排 {$count} 条死信任务");
    }
}
