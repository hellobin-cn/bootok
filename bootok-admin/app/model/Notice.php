<?php

namespace app\model;

use app\attribute\AutoResource;
use app\ui\Editor;
use app\ui\Number;
use app\ui\Radio;
use app\ui\Text;

/**
 * 公告（底座自带模块）
 * @property int $notice_id
 * @property string $title
 * @property int $status
 */
#[AutoResource(name: '公告')]
class Notice extends Model
{
    protected $table = 'notice';
    protected $primaryKey = 'notice_id';

    public function form(): array
    {
        return [
            Text::make('title', '标题')->required()->max(100),
            Editor::make('content', '内容'),
            Radio::make('status', '状态')->options([1 => '发布', 0 => '下线'])->default(1),
            Number::make('sort', '排序')->default(0),
        ];
    }
}
