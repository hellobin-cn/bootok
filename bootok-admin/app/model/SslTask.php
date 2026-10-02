<?php

namespace app\model;

use app\attribute\AutoResource;
use app\ui\Select;
use app\ui\Text;

/**
 * SSL 证书签发任务（ACME DNS-01）
 * 管理端只读展示 + 删除；创建/验证由 SslTaskController 专用端点承载
 *
 * @property int    $id
 * @property string $domains        JSON 数组
 * @property string $ca             staging/production
 * @property string $dns_provider
 * @property string $account_key_pem
 * @property string $cert_key_pem
 * @property string $order_url
 * @property string $challenges     JSON
 * @property string $fullchain_pem
 * @property string $status         created/dns_added/issued/failed
 * @property string $expires_at
 */
#[AutoResource(name: 'SSL 证书', only: ['getRows', 'getItem', 'delete'])]
class SslTask extends Model
{
    protected $table = 'sys_ssl_task';

    /** 敏感字段不可通过通用接口读取 */
    protected $hidden = ['account_key_pem', 'cert_key_pem', 'fullchain_pem'];

    public function form(): array
    {
        return [
            Text::make('domains', '域名')->required()->help('JSON 数组，如 ["haoduoya.cc","*.haoduoya.cc"]'),
            Select::make('ca', 'CA 环境')->options(['staging' => 'staging', 'production' => '生产'])->default('staging'),
        ];
    }
}
