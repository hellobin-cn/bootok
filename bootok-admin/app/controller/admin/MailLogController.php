<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\model\MailLog;
use app\service\mail\MailService;
use support\Request;
use support\Response;

/**
 * 邮件发送记录操作端点（记录 CRUD 走 /auto/mail-log）
 */
class MailLogController extends Controller
{
    public function __construct(private MailService $mailService)
    {
        parent::__construct();
    }

    /**
     * 按内容快照重发
     * POST /mail/log/{id}/resend
     */
    public function resend(Request $request, int $id): Response
    {
        $log = MailLog::find($id);
        if ($log === null) {
            return $this->error('记录不存在', '4004');
        }
        $result = $this->mailService->resend($log);

        return $result['ok'] ? $this->success($result) : $this->error($result['message']);
    }
}
