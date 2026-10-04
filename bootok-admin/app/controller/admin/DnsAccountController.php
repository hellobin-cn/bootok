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
            $message = $e->getMessage();
            // 受限策略：认证已通过（UnauthorizedOperation 发生在鉴权阶段），不影响实际签发——
            // 签发时按需探测域名归属，仅需 DescribeRecordList 等记录级权限
            if (str_contains($message, 'UnauthorizedOperation') || str_contains($message, 'AccessDenied')) {
                return $this->success([
                    'zones'   => [],
                    'message' => '密钥认证通过（当前为受限策略，无法列出域名；签发时会按需探测域名归属，不影响使用）',
                ]);
            }

            return $this->error('凭据无效：' . $message);
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
