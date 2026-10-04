<?php

namespace app\model;

use app\attribute\AutoResource;
use BootOk\Acme\Dns\AliyunProvider;
use BootOk\Acme\Dns\DnsProvider;
use BootOk\Acme\Dns\DnspodProvider;

/**
 * DNS 凭据（DNS 服务商鉴权账号）
 * 证书签发时选用，任务记录所用凭据，续期按任务回溯
 *
 * @property int    $id
 * @property string $title
 * @property string $provider  aliyun/dnspod
 * @property array  $config    凭据 JSON
 */
#[AutoResource(name: 'DNS 凭据', only: ['getRows', 'getItem', 'create', 'edit', 'delete'])]
class DnsAccount extends Model
{
    protected $table = 'sys_dns_account';

    protected $casts = [
        'config' => 'array',
    ];

    public static function providers(): array
    {
        return [
            'aliyun' => '阿里云 DNS',
            'dnspod' => '腾讯 DNSPod',
        ];
    }

    public function providerName(): string
    {
        return self::providers()[$this->provider] ?? $this->provider;
    }

    public function beforeSave(): string
    {
        // cast 属性不能间接修改，整组取出改完再赋回
        $config = is_array($this->config) ? $this->config : [];
        if (!array_key_exists($this->provider ?? '', self::providers())) {
            return 'DNS 服务商不支持';
        }
        $required = $this->provider === 'dnspod' ? ['login_token'] : ['access_key_id', 'access_key_secret'];
        foreach ($required as $field) {
            if (trim((string) ($config[$field] ?? '')) === '') {
                return "凭据项 {$field} 不能为空";
            }
        }
        $this->config = $config;

        return '';
    }

    /**
     * 构建对应服务商的 DNS 操作实例
     */
    public function provider(): DnsProvider
    {
        return match ($this->provider) {
            'dnspod' => new DnspodProvider($this->config ?? []),
            default  => new AliyunProvider($this->config ?? []),
        };
    }
}
