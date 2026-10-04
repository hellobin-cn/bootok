<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\model\MailTemplate;
use app\service\mail\MailService;
use support\Request;
use support\Response;

/**
 * 邮件模板操作端点（模板 CRUD 走 /auto/mail-template）
 */
class MailTemplateController extends Controller
{
    public function __construct(private MailService $mailService)
    {
        parent::__construct();
    }

    /**
     * 预览渲染结果：示例变量垫底，传入变量覆盖
     * POST /mail/template/preview {id, vars?}
     */
    public function preview(Request $request): Response
    {
        $template = MailTemplate::find((int) $request->post('id', 0));
        if ($template === null) {
            return $this->error('模板不存在', '4004');
        }
        $vars = (array) $request->post('vars', []) + $this->mailService->sampleVars($template);
        [$subject, $html] = $this->mailService->render($template, $vars);

        return $this->success(['subject' => $subject, 'html' => $html]);
    }

    /**
     * 按模板示例变量实发测试邮件
     * POST /mail/template/{id}/send-test {to}
     */
    public function sendTest(Request $request, int $id): Response
    {
        $template = MailTemplate::find($id);
        if ($template === null) {
            return $this->error('模板不存在', '4004');
        }
        $to = trim((string) $request->post('to', ''));
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->error('请填写正确的测试收件邮箱');
        }
        $result = $this->mailService->sendTemplate($template->code, $to, $this->mailService->sampleVars($template));

        return $result['ok'] ? $this->success($result) : $this->error($result['message']);
    }
}
