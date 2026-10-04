<?php

namespace app\service\mail;

use app\model\MailChannel;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;

/**
 * 传输器工厂：邮件渠道行 → SMTP 传输器
 *
 * 全项目唯一点名具体传输实现的位置（工厂职责所在），
 * 未来接 API 类发送服务（Postmark 等 bridge）在此加 match 分支。
 */
final class TransportFactory
{
    /**
     * 加密语义：ssl=隐式 TLS（465）/ tls=显式 STARTTLS（587）/ none=明文（25）
     */
    public static function build(MailChannel $channel): EsmtpTransport
    {
        $config = is_array($channel->config) ? $channel->config : [];
        $tls = match ((string) ($config['encryption'] ?? 'ssl')) {
            'ssl'   => true,
            'tls'   => 'tls',
            default => false,
        };

        $transport = new EsmtpTransport(
            (string) ($config['host'] ?? ''),
            (int) ($config['port'] ?? 465),
            $tls,
        );

        if ((string) ($config['username'] ?? '') !== '') {
            $transport->setUsername((string) $config['username']);
            $transport->setPassword((string) ($config['password'] ?? ''));
        }

        return $transport;
    }
}
