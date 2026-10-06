<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\model\Attachment;
use app\model\StorageChannel;
use app\service\storage\ChannelFactory;
use support\Request;
use support\Response;

/**
 * 附件统计与访问授权端点（附件 CRUD 走 /auto/attachment）
 */
class AttachmentController extends Controller
{
    /**
     * 附件概览统计
     * GET /attachment/stats
     */
    public function stats(): Response
    {
        $monthStart = date('Y-m-01 00:00:00');
        $row = Attachment::selectRaw('COUNT(*) AS total, COALESCE(SUM(size), 0) AS size')
            ->selectRaw('SUM(created_at >= ?) AS month_new', [$monthStart])
            ->first();

        return $this->success([
            'total'     => (int) ($row->total ?? 0),
            'size'      => (int) ($row->size ?? 0),
            'month_new' => (int) ($row->month_new ?? 0),
        ]);
    }

    /**
     * 批量签名地址：私有读桶（CDN 未绑定或源站鉴权）场景下，
     * 缩略图/预览需要带签名的临时 URL 才能访问
     * POST /attachment/signed-urls {ids: number[]}（最多 200）
     */
    public function signedUrls(Request $request): Response
    {
        $ids = array_map('intval', (array) $request->post('ids', []));
        $ids = array_filter(array_slice($ids, 0, 200));
        if ($ids === []) {
            return $this->error('ids 必填');
        }
        $rows = Attachment::whereIn('id', $ids)->get();
        $urls = [];
        foreach ($rows as $att) {
            try {
                $channel = $att->channel_id ? StorageChannel::find($att->channel_id) : null;
                $driver = $channel !== null ? ChannelFactory::build($channel) : null;
                // 公开读（url 即直链）走原地址；私有读驱动由 signedUrl 给临时地址
                $urls[$att->id] = $driver !== null
                    ? $driver->signedUrl(ltrim((string) $att->path, '/'), 3600)
                    : '';
            } catch (\Throwable) {
                $urls[$att->id] = '';
            }
        }

        return $this->success(['urls' => $urls]);
    }
}
