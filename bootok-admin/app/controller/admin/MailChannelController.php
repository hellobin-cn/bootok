<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\model\MailChannel;
use app\service\mail\MailChannelManager;
use app\service\mail\MailService;
use app\service\ConfigService;
use support\Request;
use support\Response;

/**
 * 邮件渠道操作端点（渠道 CRUD 走 /auto/mail-channel）
 */
class MailChannelController extends Controller
{
    public function __construct(private MailService $mailService)
    {
        parent::__construct();
    }

    /**
     * 测试「未保存」的表单配置：校验 + 实发测试邮件，不落库
     * POST /mail/channel/test {type, config, to?}
     */
    public function testConfig(Request $request): Response
    {
        $type = (string) $request->post('type', '');
        $config = (array) $request->post('config', []);
        if ($message = MailChannel::configError($type, $config)) {
            return $this->error($message);
        }
        $channel = new MailChannel(['type' => $type, 'config' => $config]);
        $to = trim((string) $request->post('to', '')) ?: $channel->sender();
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->error('请填写正确的测试收件邮箱');
        }

        return $this->runTest($channel, $to, '未保存配置');
    }

    /**
     * 按已存渠道实发测试邮件
     * POST /mail/channel/{id}/test {to?}
     */
    public function test(Request $request, int $id): Response
    {
        $channel = MailChannel::find($id);
        if ($channel === null) {
            return $this->error('渠道不存在', '4004');
        }
        $to = trim((string) $request->post('to', '')) ?: $channel->sender();
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return $this->error('请填写正确的测试收件邮箱');
        }

        return $this->runTest($channel, $to, $channel->title);
    }

    private function runTest(MailChannel $channel, string $to, string $label): Response
    {
        $siteName = (string) ConfigService::get('site_name', 'Bootok Admin');
        $host = (string) ($channel->config['host'] ?? '');
        $port = (string) ($channel->config['port'] ?? '');
        $encryption = MailChannel::encrypts()[(string) ($channel->config['encryption'] ?? 'ssl')] ?? '';
        $time = date('Y-m-d H:i:s');
        $html = <<<HTML
<div style="background:#f6f8fa;padding:40px 16px;font-family:-apple-system,'Segoe UI','PingFang SC','Microsoft YaHei',sans-serif;">
  <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;padding:32px 40px;box-shadow:0 8px 30px rgba(15,23,42,.08);">
    <h2 style="margin:0 0 16px;font-size:20px;color:#1f2937;">{$siteName} 邮件渠道测试</h2>
    <p style="margin:0 0 8px;font-size:14px;line-height:1.8;color:#6b7280;">这是一封渠道测试邮件，收到即说明渠道配置有效。</p>
    <p style="margin:0;font-size:13px;line-height:2;color:#9ca3af;">
      渠道：{$label}<br>
      SMTP：{$host}:{$port}（{$encryption}）<br>
      发送时间：{$time}
    </p>
  </div>
</div>
HTML;
        $result = $this->mailService->send($to, "【{$siteName}】邮件渠道测试", $html, true, $channel);

        return $result['ok'] ? $this->success($result) : $this->error($result['message']);
    }
}
