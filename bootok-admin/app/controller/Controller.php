<?php

namespace app\controller;

use app\entity\ServiceResult;
use app\enum\Code;
use support\Request;
use support\Response;

/**
 * 控制器基类
 * 持有请求上下文与统一响应
 */
class Controller
{
    protected ?Request $request = null;
    protected string $requestId = '';
    protected int $adminId = 0;
    protected string $adminName = '';
    protected int $groupId = 0;
    protected int $isSuper = 0;

    public function __construct()
    {
        $this->request = request();
        $this->requestId = $this->request->header('request-id', '');
        $this->adminId = $this->request->getCustomData('adminId', 0);
        $this->adminName = $this->request->getCustomData('adminName', '');
        $this->groupId = $this->request->getCustomData('groupId', 0);
        $this->isSuper = $this->request->getCustomData('isSuper', 0);
    }

    protected function uniResponse(ServiceResult $res): Response
    {
        if ($res->code == Code::SUCCESS->value) {
            return $this->success($res->data, $res->message);
        }
        return $this->error($res->message, $res->code, $res->data);
    }

    /**
     * 返回正确的json响应
     */
    protected function success($data = null, string $message = '操作成功', string $code = Code::SUCCESS->value, array $header = []): Response
    {
        return json_success($this->requestId, $data, $message, $code, $header);
    }

    /**
     * 返回错误的json响应
     */
    protected function error(string $message = '操作失败', string $code = Code::FAIL->value, $data = null, array $header = []): Response
    {
        return json_error($this->requestId, $message, $code, $data, $header);
    }
}
