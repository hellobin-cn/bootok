<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\model\SslDeploy;
use app\service\SslDeployService;
use support\Request;
use support\Response;

/**
 * SSL 部署目标操作端点（目标 CRUD 走 /auto/ssl-deploy）
 */
class SslDeployController extends Controller
{
    /**
     * 立即部署：对绑定域名下最近一次签发成功的证书重新投递
     * POST /ssl/deploy/{id}/run
     */
    public function run(Request $request, int $id): Response
    {
        $target = SslDeploy::find($id);
        if ($target === null) {
            return $this->error('部署目标不存在', '4004');
        }
        try {
            SslDeployService::runNow($target);

            return $this->success([
                'status'      => $target->last_status,
                'deployed_at' => $target->last_deployed_at,
            ]);
        } catch (\Throwable $e) {
            return $this->error('部署失败：' . $e->getMessage());
        }
    }

    /**
     * 连通性测试（不执行 reload）
     * POST /ssl/deploy/{id}/test
     */
    public function test(Request $request, int $id): Response
    {
        $target = SslDeploy::find($id);
        if ($target === null) {
            return $this->error('部署目标不存在', '4004');
        }
        $result = SslDeployService::test($target);

        return $result['ok'] ? $this->success($result) : $this->error($result['message']);
    }
}
