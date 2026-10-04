<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\model\StorageChannel;
use app\storage\ChannelManager;
use support\Request;
use support\Response;

/**
 * 存储渠道操作端点（渠道 CRUD 走 /auto/storage-channel）
 */
class StorageChannelController extends Controller
{
    /**
     * 配置自检：写读删一个探针对象，验证凭据、权限与网络连通
     * POST /storage/channel/{id}/test
     */
    public function test(Request $request, int $id): Response
    {
        $channel = StorageChannel::find($id);
        if ($channel === null) {
            return $this->error('渠道不存在', '4004');
        }
        try {
            $result = (new ChannelManager())->forChannel($channel)->test();
        } catch (\Throwable $e) {
            return $this->error('测试失败：' . $e->getMessage());
        }

        return $result['ok'] ? $this->success($result) : $this->error($result['message']);
    }
}
