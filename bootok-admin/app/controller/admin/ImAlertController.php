<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\service\im\ImAlertService;
use support\Response;

/**
 * IM 告警端点：推送通道连通性测试（仅超管，配置在参数设置-告警）
 */
class ImAlertController extends Controller
{
    public function __construct(private readonly ImAlertService $alerts)
    {
        parent::__construct();
    }

    /**
     * POST /im/test
     */
    public function test(): Response
    {
        if (!$this->request->getCustomData('isSuper', 0)) {
            return $this->error('仅超级管理员可测试告警通道');
        }
        $result = $this->alerts->test();
        if ($result['ok']) {
            return $this->success(['ok' => true], $result['message']);
        }
        return $this->error($result['message']);
    }
}
