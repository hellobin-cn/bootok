<?php

namespace app\model;

use app\attribute\AutoResource;
use app\enum\ArticleStatus;
use app\ui\Editor;
use app\ui\Image;
use app\ui\Number;
use app\ui\Select;
use app\ui\Text;

/**
 * 文章，业务模块示例：一个模型文件即完整资源（路由+表单+权限点）
 * @property int $article_id
 * @property string $title
 * @property string $cover
 * @property string $content
 * @property int $status
 * @property int $sort
 */
#[AutoResource(name: '文章')]
class Article extends Model
{
    protected $table = 'article';
    protected $primaryKey = 'article_id';

    public function form(): array
    {
        return [
            Text::make('title', '标题')->required()->max(100),
            Image::make('cover', '封面图'),
            Editor::make('content', '内容'),
            Select::make('status', '状态')->options(ArticleStatus::class)->default(ArticleStatus::Up->value),
            Number::make('sort', '排序')->default(0),
        ];
    }
}
