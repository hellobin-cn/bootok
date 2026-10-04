<?php

namespace app\model;

use app\attribute\AutoResource;
use app\service\AttachmentService;

/**
 * 附件（上传即落库，只读资源；数据资产为引用计数/清理预留）
 * @property int    $id
 * @property int    $channel_id   存储渠道
 * @property string $path
 * @property string $original_name
 * @property int    $size
 * @property string $ext
 */
#[AutoResource(name: '附件', only: ['getRows', 'getItem', 'edit', 'delete'], read: 'attachment.rows')]
class Attachment extends Model
{
    protected $table = 'sys_attachment';

    public $timestamps = false;

    /** 附件名属敏感度低的展示字段，无需脱敏 */
    protected array $mask = [];

    /** 列表携带按渠道路由生成的访问 URL */
    protected $appends = ['url'];

    public function getUrlAttribute(): string
    {
        return AttachmentService::urlFor($this);
    }
    public function afterDeleted(): void
    {
        // 行删除时同步清理存储对象（对象不存在由驱动静默处理；渠道不可达不阻断删除流程）
        try {
            \support\Container::get(\app\service\AttachmentService::class)->deleteObject($this);
        } catch (\Throwable $e) {
            \support\Log::warning('attachment.object_delete_failed', ['id' => $this->id, 'error' => $e->getMessage()]);
        }
    }
}
