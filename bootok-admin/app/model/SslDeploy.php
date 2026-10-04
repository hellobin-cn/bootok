<?php

namespace app\model;

use app\attribute\AutoResource;

/**
 * SSL 部署目标（证书投递通道）
 * 通用 CRUD 由 /auto/ssl-deploy 承载；立即部署/连通性测试走 SslDeployController 专用端点
 *
 * @property int    $id
 * @property string $title
 * @property string $domain          绑定主域名
 * @property string $channel         ssh（webhook 预留）
 * @property string $mode            local/remote
 * @property array  $config          通道配置 JSON
 * @property int    $enabled
 * @property string $last_status     success/failed
 * @property string $last_deployed_at
 * @property string $last_error
 */
#[AutoResource(name: 'SSL 部署目标', only: ['getRows', 'getItem', 'create', 'edit', 'delete'])]
class SslDeploy extends Model
{
    protected $table = 'sys_ssl_deploy';

    protected $casts = [
        'config'  => 'array',
        'enabled' => 'boolean',
    ];

    public function beforeSave(): string
    {
        // cast 属性不能间接修改（Indirect modification of overloaded property），整组取出改完再赋回
        $config = is_array($this->config) ? $this->config : [];
        $channel = $this->channel === 'cdn' ? 'cdn' : 'ssh';
        $this->channel = $channel;

        if ($channel === 'cdn') {
            // 云 CDN 通道：mode = 云厂商（aliyun/tencent）
            if (!in_array($this->mode, ['aliyun', 'tencent'], true)) {
                return 'CDN 服务商仅支持 aliyun / tencent';
            }
            if (trim((string) ($config['cdn_domain'] ?? '')) === '') {
                return '配置项 cdn_domain 不能为空';
            }
            $required = $this->mode === 'tencent' ? ['secret_id', 'secret_key'] : ['access_key_id', 'access_key_secret'];
            foreach ($required as $field) {
                if (trim((string) ($config[$field] ?? '')) === '') {
                    return "配置项 {$field} 不能为空";
                }
            }
            $this->config = $config;

            return '';
        }

        // SSH 通道：mode = local/remote
        $mode = $this->mode === 'remote' ? 'remote' : 'local';
        $this->mode = $mode;
        $required = $mode === 'remote'
            ? ['host', 'username', 'private_key', 'cert_path', 'key_path', 'reload_cmd']
            : ['cert_path', 'key_path', 'reload_cmd'];
        foreach ($required as $field) {
            if (trim((string) ($config[$field] ?? '')) === '') {
                return "配置项 {$field} 不能为空";
            }
        }
        if ($mode === 'remote') {
            $port = (int) ($config['port'] ?? 22);
            $config['port'] = $port > 0 && $port <= 65535 ? $port : 22;
        }
        $this->config = $config;

        return '';
    }
}
