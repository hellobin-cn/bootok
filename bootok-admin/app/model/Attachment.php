<?php

namespace app\model;

use app\attribute\AutoResource;

/**
 * 附件（上传即落库，只读资源；数据资产为引用计数/清理预留）
 * @property string $path
 * @property string $original_name
 */
#[AutoResource(name: '附件', only: ['getRows', 'getItem'], read: 'attachment.rows')]
class Attachment extends Model
{
    protected $table = 'sys_attachment';

    public $timestamps = false;

    /** 附件名属敏感度低的展示字段，无需脱敏 */
    protected array $mask = [];
}
