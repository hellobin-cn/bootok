<?php

namespace app\model;

use app\attribute\AutoResource;
use app\service\ConfigService;
use app\service\mail\MailChannelManager;

/**
 * 邮件渠道（SMTP 账号，凭据界面可管理）
 *
 * @property int    $id
 * @property string $title
 * @property string $type       smtp（预留 API 类扩展）
 * @property array  $config     渠道配置 JSON：host/port/encryption/username/password/from_address/from_name
 * @property bool   $is_default 默认发送渠道
 */
#[AutoResource(name: '邮件渠道', only: ['getRows', 'getItem', 'create', 'edit', 'delete'])]
class MailChannel extends Model
{
    protected $table = 'sys_mail_channel';

    protected $casts = [
        'config'     => 'array',
        'is_default' => 'boolean',
    ];

    public static function types(): array
    {
        return [
            'smtp' => 'SMTP 邮箱',
        ];
    }

    public static function encrypts(): array
    {
        return [
            'ssl'  => 'SSL（隐式 TLS，465）',
            'tls'  => 'STARTTLS（587）',
            'none' => '不加密（25）',
        ];
    }

    public function beforeSave(): string
    {
        // cast 属性不能间接修改，整组取出改完再赋回
        $config = is_array($this->config) ? $this->config : [];
        if ($message = self::configError((string) ($this->type ?? ''), $config)) {
            return $message;
        }
        $this->config = $config;

        return '';
    }

    /**
     * 渠道配置校验（保存与「测试当前配置」共用；返回错误消息，空串为通过）
     *
     * @param array<string, mixed> $config
     */
    public static function configError(string $type, array $config): string
    {
        if (!array_key_exists($type, self::types())) {
            return '邮件渠道类型不支持';
        }
        foreach (['host', 'port', 'from_address'] as $field) {
            if (trim((string) ($config[$field] ?? '')) === '') {
                return "配置项 {$field} 不能为空";
            }
        }
        if (!filter_var((string) $config['from_address'], FILTER_VALIDATE_EMAIL)) {
            return '发件人地址格式不正确';
        }
        if (!array_key_exists((string) ($config['encryption'] ?? 'ssl'), self::encrypts())) {
            return '加密方式仅支持 ssl/tls/none';
        }
        if ((int) $config['port'] <= 0 || (int) $config['port'] > 65535) {
            return '端口不正确';
        }

        return '';
    }

    public function afterSaved(): void
    {
        // 唯一默认渠道：置默认时清掉其他行
        if ($this->is_default) {
            self::where('id', '!=', $this->id)->where('is_default', true)->update(['is_default' => false]);
        }
        // 渠道配置变更即时生效（Mailer 实例缓存失效）
        MailChannelManager::forget((int) $this->id);
    }

    public function afterDeleted(): void
    {
        MailChannelManager::forget((int) $this->id);
    }

    /**
     * 发件人地址：from_address 缺省回退 SMTP 账号（多数服务商两者一致）
     */
    public function sender(): string
    {
        $config = is_array($this->config) ? $this->config : [];

        return (string) ($config['from_address'] ?? '') ?: (string) ($config['username'] ?? '');
    }

    /**
     * 发件人名称：from_name 缺省取站点名称（配置中心）
     */
    public function senderName(): string
    {
        return (string) ($this->config['from_name'] ?? '') ?: (string) ConfigService::get('site_name', 'Bootok Admin');
    }
}
