<?php

namespace app\service\im;

use app\service\ConfigService;
use BootOk\Http;
use support\Log;
use Throwable;

/**
 * IM 告警推送：站内消息并行出口，推送到群机器人（钉钉/企业微信/飞书）
 * webhook 等运营项存配置中心（group=告警）；send 永不抛出，推送失败只记日志
 */
class ImAlertService
{
    public const TYPES = ['none', 'dingtalk', 'wecom', 'feishu'];

    /**
     * 推送一条告警文本（配置未启用/类型 none 时静默跳过）
     */
    public function send(string $title, string $content = ''): void
    {
        try {
            $type = (string) ConfigService::get('alert_im_type', 'none');
            $webhook = (string) ConfigService::get('alert_im_webhook', '');
            if ($type === '' || $type === 'none' || $webhook === '') {
                return;
            }
            $this->post($type, $webhook, (string) ConfigService::get('alert_im_secret', ''), $this->text($title, $content));
        } catch (Throwable $e) {
            Log::warning('IM 告警推送失败: ' . $e->getMessage());
        }
    }

    /**
     * 连通性测试（消息中心页「测试告警推送」按钮）
     *
     * @return array{ok: bool, message: string}
     */
    public function test(): array
    {
        $type = (string) ConfigService::get('alert_im_type', 'none');
        $webhook = (string) ConfigService::get('alert_im_webhook', '');
        if ($type === '' || $type === 'none' || !in_array($type, self::TYPES, true)) {
            return ['ok' => false, 'message' => '请先在参数设置-告警中选择通道类型'];
        }
        if ($webhook === '') {
            return ['ok' => false, 'message' => '请先在参数设置-告警中填写 Webhook 地址'];
        }
        [$ok, $message] = $this->post($type, $webhook, (string) ConfigService::get('alert_im_secret', ''), 'bootok 告警通道测试消息');

        return ['ok' => $ok, 'message' => $message];
    }

    /**
     * 按平台协议投递，返回 [ok, 平台回执消息]
     */
    private function post(string $type, string $webhook, string $secret, string $text): array
    {
        [$url, $payload] = match ($type) {
            'dingtalk' => [$this->signedUrl($webhook, $secret), ['msgtype' => 'text', 'text' => ['content' => $text]]],
            'wecom'    => [$webhook, ['msgtype' => 'text', 'text' => ['content' => $text]]],
            'feishu'   => $this->feishuPayload($webhook, $secret, $text),
            default    => throw new \InvalidArgumentException("未知 IM 类型: {$type}"),
        };

        $resp = Http::asJson()->timeout(5)->post($url, $payload);
        $body = json_decode((string) $resp->body(), true) ?: [];
        // 钉钉/企微 errcode==0，飞书 code==0
        $ok = $resp->ok() && (int) ($body['errcode'] ?? $body['code'] ?? -1) === 0;
        $message = (string) ($body['errmsg'] ?? $body['msg'] ?? ($ok ? '推送成功' : 'HTTP ' . $resp->status()));

        return [$ok, $message];
    }

    /** 钉钉加签：timestamp\nsecret 的 HMAC-SHA256 挂查询参数 */
    private function signedUrl(string $webhook, string $secret): string
    {
        if ($secret === '') {
            return $webhook;
        }
        $ts = (int) (microtime(true) * 1000);
        $sign = urlencode(base64_encode(hash_hmac('sha256', "{$ts}\n{$secret}", $secret, true)));

        return $webhook . (str_contains($webhook, '?') ? '&' : '?') . http_build_query(['timestamp' => $ts, 'sign' => $sign]);
    }

    /** 飞书加签：sign 放 body（timestamp\nsecret HMAC-SHA256） */
    private function feishuPayload(string $webhook, string $secret, string $text): array
    {
        $ts = time();
        $sign = base64_encode(hash_hmac('sha256', "{$ts}\n{$secret}", $secret, true));
        $payload = ['timestamp' => (string) $ts, 'msg_type' => 'text', 'content' => ['text' => $text]];
        if ($secret !== '') {
            $payload['sign'] = $sign;
        }

        return [$webhook, $payload];
    }

    private function text(string $title, string $content): string
    {
        $text = '【bootok】' . $title;
        if ($content !== '') {
            $text .= "\n" . mb_substr($content, 0, 500);
        }

        return $text;
    }
}
