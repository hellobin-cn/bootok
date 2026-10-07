<?php

namespace app\service\wechat;

use app\model\Article;
use app\model\WechatPublish;
use app\service\MessageService;
use Throwable;

/**
 * 文章 → 公众号发布：封面/正文图片转存微信 → 建草稿 → 可选提交发布
 * 记录全生命周期落 sys_wechat_publish，结果经消息中心通知超管
 */
class Publisher
{
    public function __construct(
        private readonly OfficialAccountService $official,
        private readonly MessageService $messages
    ) {
    }

    /**
     * 队列动作入口：按文章 id 发布（建草稿，autoPublish 时提交发布）
     */
    public function runJob(int $articleId, bool $autoPublish, string $digest): void
    {
        $article = Article::find($articleId);
        if ($article === null) {
            throw new \RuntimeException("文章不存在: {$articleId}");
        }
        $row = WechatPublish::create([
            'article_id' => $articleId,
            'title'      => mb_substr((string) $article->title, 0, 200),
            'status'     => 0,
        ]);
        try {
            $result = $this->publish($article, $autoPublish, $digest);
            $row->draft_media_id = $result['draft_media_id'];
            $row->publish_id = $result['publish_id'];
            $row->status = $result['publish_id'] !== '' ? 2 : 1;
            $row->save();
            $this->messages->sendToSupers(
                "公众号发布完成：《{$article->title}》",
                $result['publish_id'] !== ''
                    ? "已提交发布（publish_id={$result['publish_id']}），发布结果以消息中心通知为准"
                    : '草稿已创建，请到公众平台预览后发布',
                MessageService::TYPE_NOTICE
            );
        } catch (Throwable $e) {
            $row->status = 0;
            $row->error = mb_substr($e->getMessage(), 0, 500);
            $row->save();
            $this->messages->sendToSupers(
                "公众号发布失败：《{$article->title}》",
                $e->getMessage(),
                MessageService::TYPE_TASK
            );
            throw $e;
        }
    }

    /**
     * @return array{draft_media_id: string, publish_id: string}
     */
    private function publish(Article $article, bool $autoPublish, string $digest): array
    {
        if ((string) $article->cover === '') {
            throw new \RuntimeException('文章未设置封面图，公众号草稿必须携带封面');
        }

        // 封面 → 永久素材 → thumb_media_id
        $thumbMediaId = $this->official->uploadThumb((string) $article->cover);

        // 正文图片转存微信（外站图微信不渲染）
        $content = $this->transferContentImages((string) ($article->content ?? ''));

        $digest = $digest !== '' ? $digest : mb_substr(strip_tags($content), 0, 54);
        $mediaId = $this->official->addDraft([
            [
                'title'                => mb_substr((string) $article->title, 0, 64),
                'author'               => 'bootok',
                'digest'               => mb_substr($digest, 0, 120),
                'content'              => $content,
                'thumb_media_id'       => $thumbMediaId,
                'need_open_comment'    => 1,
                'only_fans_can_comment'=> 0,
            ],
        ]);

        $publishId = '';
        if ($autoPublish) {
            $publishId = $this->official->freepublishSubmit($mediaId);
        }
        return ['draft_media_id' => $mediaId, 'publish_id' => $publishId];
    }

    /** 正文中的站外/本站图片全部转存为微信素材 URL */
    private function transferContentImages(string $content): string
    {
        preg_match_all('/<img[^>]+src="([^"]+)"/i', $content, $matches);
        $done = [];
        foreach ($matches[1] as $src) {
            if (isset($done[$src])) {
                continue;
            }
            // 微信域图片已是合法素材 URL，跳过
            if (str_contains($src, 'mmbiz.qpic.cn')) {
                continue;
            }
            $wechatUrl = $this->official->uploadContentImage(html_entity_decode($src));
            $content = str_replace($src, $wechatUrl, $content);
            $done[$src] = true;
        }
        return $content;
    }
}
