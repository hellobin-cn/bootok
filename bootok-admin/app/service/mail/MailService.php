<?php

namespace app\service\mail;

use app\model\MailChannel;
use app\model\MailLog;
use app\model\MailTemplate;
use app\service\ConfigService;
use RuntimeException;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;
use Throwable;

/**
 * 邮件发送门面（业务代码唯一入口）
 *
 * 渠道选择：null=默认渠道；int=按 id 路由（缺省回退默认）；MailChannel=直接指定（测试端点用）。
 * 每次发送（成功/失败）落 sys_mail_log，含渲染后快照，供界面排查与记录级重发。
 */
class MailService
{
    public function __construct(private MailChannelManager $channels)
    {
    }

    /**
     * 发送纯文本邮件
     * @return array{ok: bool, message: string}
     */
    public function sendText(array|string $to, string $subject, string $text, int|string|MailChannel|null $channel = null, array $options = []): array
    {
        return $this->send($to, $subject, $text, false, $channel, $options);
    }

    /**
     * 按模板发送：code 定位模板，渲染（业务变量 + 全局变量 site_name/year）后发送
     *
     * @param string $code  模板标识（sys_mail_template.code）
     * @param array  $vars  业务变量（值默认做 HTML 转义；模板已声明的变量未传时保留 {$var} 原文便于排查）
     * @throws RuntimeException 模板不存在（引用错误应在开发期暴露）
     * @return array{ok: bool, message: string}
     */
    public function sendTemplate(string $code, array|string $to, array $vars = [], int|string|MailChannel|null $channel = null, array $options = []): array
    {
        $template = MailTemplate::where('code', $code)->first();
        if ($template === null) {
            throw new RuntimeException("邮件模板 {$code} 不存在");
        }
        [$subject, $html] = $this->render($template, $vars);

        return $this->send($to, $subject, $html, true, $channel, $options + ['template_code' => $code]);
    }

    /**
     * 发送邮件（核心方法）
     *
     * @param array|string $to 收件人，单个邮箱或数组
     * @param array $options 可选参数：
     *   - cc/bcc/reply_to string|array 地址
     *   - attachments array 元素为文件路径，或 ['path' => '/a.pdf', 'name' => 'a.pdf', 'mime' => 'application/pdf']
     *   - template_code string 发送记录归因的模板标识（sendTemplate 自动带）
     * @return array{ok: bool, message: string}
     */
    public function send(array|string $to, string $subject, string $content, bool $isHtml = true, int|string|MailChannel|null $channel = null, array $options = []): array
    {
        $startedAt = microtime(true);
        $recipients = implode(', ', array_filter(array_map('strval', (array) $to)));
        $templateCode = (string) ($options['template_code'] ?? '');

        if (!$channel instanceof MailChannel) {
            $channel = $this->channels->channelFor($channel);
        }
        if ($channel === null) {
            return $this->finish(null, $templateCode, $recipients, $subject, '', '未配置邮件渠道', $startedAt);
        }
        if ($channel->sender() === '') {
            return $this->finish($channel, $templateCode, $recipients, $subject, '', '渠道缺少发件人地址', $startedAt);
        }

        try {
            $email = (new Email())
                ->from(new Address($channel->sender(), $channel->senderName()))
                ->subject($subject);

            foreach ((array) $to as $address) {
                $email->addTo($address);
            }
            $this->addAddresses($email, 'cc', $options['cc'] ?? []);
            $this->addAddresses($email, 'bcc', $options['bcc'] ?? []);
            $this->addAddresses($email, 'replyTo', $options['reply_to'] ?? []);

            $isHtml ? $email->html($content) : $email->text($content);

            foreach ((array) ($options['attachments'] ?? []) as $attachment) {
                if (is_array($attachment)) {
                    $email->attachFromPath($attachment['path'], $attachment['name'] ?? null, $attachment['mime'] ?? null);
                } else {
                    $email->attachFromPath($attachment);
                }
            }

            $this->channels->mailerFor($channel)->send($email);

            return $this->finish($channel, $templateCode, $recipients, $subject, $content, '', $startedAt);
        } catch (Throwable $e) {
            return $this->finish($channel, $templateCode, $recipients, $subject, $content, $e->getMessage(), $startedAt);
        }
    }

    /**
     * 按快照重发（不重渲染，避免模板已改导致内容漂移；渠道已删时回退默认渠道）
     */
    public function resend(MailLog $log): array
    {
        $to = array_values(array_filter(array_map('trim', explode(',', (string) $log->to_address))));

        return $this->send(
            $to,
            (string) $log->subject,
            (string) $log->body,
            true,
            $log->channel_id,
            ['template_code' => (string) $log->template_code]
        );
    }

    /**
     * 渲染模板：返回 [subject, html]
     *
     * 变量值默认 htmlspecialchars（模板变量均为验证码/用户名/URL 等短文本；body 本身是管理员写的可信 HTML）。
     * 未提供的变量保留 {$var} 原文，便于从发出内容反查漏传。
     */
    public function render(MailTemplate $template, array $vars = []): array
    {
        $values = $this->globalVars();
        foreach ($vars as $key => $value) {
            $values[$key] = $value;
        }

        $from = [];
        $to = [];
        foreach ($values as $key => $value) {
            $from[] = '{$' . $key . '}';
            $to[] = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
        }

        return [
            str_replace($from, $to, (string) $template->subject),
            str_replace($from, $to, (string) $template->body),
        ];
    }

    /**
     * 按变量声明生成示例值（预览与测试发送用；code/ttl 语义化让预览接近真实效果）
     *
     * @return array<string, string>
     */
    public function sampleVars(MailTemplate $template): array
    {
        $sample = [];
        foreach ((array) ($template->variables ?? []) as $item) {
            $name = (string) ($item['name'] ?? '');
            if ($name === '') {
                continue;
            }
            $sample[$name] = trim((string) ($item['label'] ?? '')) ?: $name;
        }
        if (isset($sample['code'])) {
            $sample['code'] = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        }
        if (isset($sample['ttl'])) {
            $sample['ttl'] = '10';
        }

        return $sample;
    }

    /**
     * 全局自动变量（配置中心 site_name + 当前年份）
     */
    private function globalVars(): array
    {
        return [
            'site_name' => (string) ConfigService::get('site_name', 'Bootok Admin'),
            'year'      => date('Y'),
        ];
    }

    private function addAddresses(Email $email, string $method, array|string $addresses): void
    {
        foreach ((array) $addresses as $address) {
            if ($address) {
                $email->{$method}($address);
            }
        }
    }

    /**
     * 落发送记录并返回统一结果
     */
    private function finish(?MailChannel $channel, string $templateCode, string $to, string $subject, string $body, string $error, float $startedAt): array
    {
        $ok = $error === '';
        MailLog::create([
            'channel_id'    => $channel?->id,
            'template_code' => $templateCode,
            'to_address'    => $to,
            'subject'       => $subject,
            'body'          => $body,
            'status'        => $ok ? 1 : 0,
            'error'         => $error,
            'cost_ms'       => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return ['ok' => $ok, 'message' => $ok ? '发送成功' : ($error ?: '发送失败')];
    }
}
