<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\model\DnsAccount;
use support\Request;
use support\Response;

/**
 * DNS 凭据操作端点（凭据 CRUD 走 /auto/dns-account）
 */
class DnsAccountController extends Controller
{
    /**
     * 连通性测试：用该凭据列出账号下的解析域名
     * POST /ssl/dns-account/{id}/test
     */
    public function test(Request $request, int $id): Response
    {
        $account = DnsAccount::find($id);
        if ($account === null) {
            return $this->error('凭据不存在', '4004');
        }
        try {
            $zones = $account->provider()->listZones();
        } catch (\Throwable $e) {
            return $this->error('凭据无效：' . $e->getMessage());
        }
        if ($zones === []) {
            return $this->error('凭据认证通过，但账号下没有解析域名');
        }
        $preview = implode('、', array_slice($zones, 0, 5)) . (count($zones) > 5 ? ' 等' : '');

        return $this->success([
            'zones' => $zones,
            'message' => '凭据有效，账号下域名：' . $preview,
        ]);
    }
}
