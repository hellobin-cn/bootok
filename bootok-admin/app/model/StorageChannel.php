<?php

namespace app\model;

use app\attribute\AutoResource;
use app\storage\ChannelManager;

/**
 * 存储渠道（附件的存储后端：local/oss/cos，可绑定 CDN）
 *
 * @property int    $id
 * @property string $title
 * @property string $type       local/oss/cos
 * @property array  $config     渠道配置 JSON
 * @property bool   $is_default 上传使用的默认渠道
 */
#[AutoResource(name: '存储渠道', only: ['getRows', 'getItem', 'create', 'edit', 'delete'])]
class StorageChannel extends Model
{
    protected $table = 'sys_storage_channel';

    protected $casts = [
        'config'    => 'array',
        'is_default' => 'boolean',
    ];

    public static function types(): array
    {
        return [
            'local' => '本地存储',
            'oss'   => '阿里云 OSS',
            'cos'   => '腾讯云 COS',
        ];
    }

    public function beforeSave(): string
    {
        // cast 属性不能间接修改，整组取出改完再赋回
        $config = is_array($this->config) ? $this->config : [];
        if (!array_key_exists($this->type ?? '', self::types())) {
            return '存储类型不支持';
        }
        $required = match ($this->type) {
            'oss'   => ['bucket', 'endpoint', 'access_key_id', 'access_key_secret'],
            'cos'   => ['bucket', 'region', 'secret_id', 'secret_key'],
            default => [],
        };
        foreach ($required as $field) {
            if (trim((string) ($config[$field] ?? '')) === '') {
                return "配置项 {$field} 不能为空";
            }
        }
        if (($config['cdn_domain'] ?? '') !== '' && !in_array($config['scheme'] ?? 'https', ['http', 'https'], true)) {
            return 'CDN 协议仅支持 http/https';
        }
        $this->config = $config;

        return '';
    }

    public function afterSaved(): void
    {
        // 唯一默认渠道：置默认时清掉其他行
        if ($this->is_default) {
            self::where('id', '!=', $this->id)->where('is_default', true)->update(['is_default' => false]);
        }
        // 渠道配置变更即时生效（驱动/渠道行缓存失效）
        ChannelManager::forget((int) $this->id);
    }

    public function afterDeleted(): void
    {
        ChannelManager::forget((int) $this->id);
    }
}
