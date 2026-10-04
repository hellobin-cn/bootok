<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\model\Attachment;
use support\Request;
use support\Response;

/**
 * 附件统计端点（附件 CRUD 走 /auto/attachment）
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
}
