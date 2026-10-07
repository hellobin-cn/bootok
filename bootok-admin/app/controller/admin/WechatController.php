<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\enum\Code;
use app\model\Article;
use app\model\WechatPublish;
use app\service\wechat\OfficialAccountService;
use support\Request;
use support\Response;
use Throwable;

/**
 * 公众号管理（仅超管）：连通状态 / 自定义菜单 / 发布记录与发起
 */
class WechatController extends Controller
{
    public function __construct(private readonly OfficialAccountService $official)
    {
        parent::__construct();
    }

    private function guard(): ?Response
    {
        if (!$this->request->getCustomData('isSuper', 0)) {
            return $this->error('仅超级管理员可管理公众号', Code::FORBIDDEN->value);
        }
        return null;
    }

    /**
     * GET /wechat/status：配置完整性与连通状态
     */
    public function status(): Response
    {
        if ($res = $this->guard()) {
            return $res;
        }
        $config = \app\service\ConfigService::group('公众号');
        $configured = ($config['wechat_appid'] ?? '') !== '' && ($config['wechat_appsecret'] ?? '') !== '';
        $tokenOk = false;
        $tokenError = '';
        if ($configured) {
            try {
                $tokenOk = $this->official->getAccessToken() !== '';
            } catch (Throwable) {
            }
            if (!$tokenOk) {
                // 再取一次失败上下文（getAccessToken 内部已记日志，这里给前端友好文案）
                $tokenError = '获取 access_token 失败（检查 AppID/AppSecret 与公众号后台 IP 白名单）';
            }
        }
        return $this->success([
            'appid'        => $config['wechat_appid'] ?? '',
            'token'        => $config['wechat_token'] ?? '',
            'welcome'      => $config['wechat_welcome'] ?? '',
            'configured'   => $configured,
            'token_ok'     => $tokenOk,
            'token_error'  => $tokenError,
            'callback_url' => '/client/v1/wechat/callback（部署域名为前缀，填入公众平台服务器配置，明文模式）',
        ]);
    }

    /**
     * GET /wechat/menu
     */
    public function menuGet(): Response
    {
        if ($res = $this->guard()) {
            return $res;
        }
        $res = $this->official->menuGet();
        if (isset($res['errcode']) && (int) $res['errcode'] !== 0) {
            return $this->error('菜单读取失败: ' . ($res['errmsg'] ?? ''));
        }
        return $this->success($res);
    }

    /**
     * POST /wechat/menu {button: [...]}
     */
    public function menuSave(Request $request): Response
    {
        if ($res = $this->guard()) {
            return $res;
        }
        $button = $request->post('button');
        if (!is_array($button) || !$button) {
            return $this->error('菜单不能为空', Code::PARAM_VALIDATE->value);
        }
        if (count($button) > 3) {
            return $this->error('一级菜单最多 3 个', Code::PARAM_VALIDATE->value);
        }
        foreach ($button as $item) {
            if (($item['sub_button'] ?? []) && count($item['sub_button']) > 5) {
                return $this->error('二级菜单最多 5 个', Code::PARAM_VALIDATE->value);
            }
        }
        $res = $this->official->menuCreate($button);
        if ((int) ($res['errcode'] ?? -1) !== 0) {
            return $this->error('菜单保存失败: ' . ($res['errmsg'] ?? json_encode($res, JSON_UNESCAPED_UNICODE)));
        }
        return $this->success([], '菜单已发布（手机端可能延迟几分钟生效）');
    }

    /**
     * DELETE /wechat/menu
     */
    public function menuDelete(): Response
    {
        if ($res = $this->guard()) {
            return $res;
        }
        $res = $this->official->menuDelete();
        if ((int) ($res['errcode'] ?? -1) !== 0) {
            return $this->error('菜单删除失败: ' . ($res['errmsg'] ?? ''));
        }
        return $this->success([], '菜单已删除');
    }

    /**
     * GET /wechat/publish/rows：发布记录（联文章标题）
     */
    public function publishRows(): Response
    {
        if ($res = $this->guard()) {
            return $res;
        }
        $pageSize = max(1, min(100, (int) $this->request->input('page_size', 15)));
        $paginator = WechatPublish::orderBy('id', 'desc')
            ->paginate($pageSize, ['*'], 'page', (int) $this->request->input('page', 1));
        return $this->success($paginator);
    }

    /**
     * POST /wechat/publish {article_id, auto_publish, digest}：入队列异步发布
     */
    public function publishRun(Request $request): Response
    {
        if ($res = $this->guard()) {
            return $res;
        }
        $articleId = (int) $request->post('article_id');
        $article = Article::find($articleId);
        if ($article === null) {
            return $this->error('文章不存在');
        }
        if ((string) $article->cover === '') {
            return $this->error('文章未设置封面图，请先在编辑页上传封面');
        }
        $autoPublish = (bool) $request->post('auto_publish', false);
        $digest = mb_substr((string) $request->post('digest', ''), 0, 120);
        (new \app\service\queue\QueueService())->dispatch('wechat.publish_article', [
            'article_id'   => $articleId,
            'auto_publish' => $autoPublish,
            'digest'       => $digest,
        ]);
        return $this->success([], '已加入发布队列，结果将通过消息中心通知');
    }

    /**
     * POST /wechat/publish/{id}/refresh：向微信查询发布任务状态并回写
     */
    public function publishRefresh(int $id): Response
    {
        if ($res = $this->guard()) {
            return $res;
        }
        $row = WechatPublish::find($id);
        if ($row === null || $row->publish_id === '') {
            return $this->error('该记录无发布任务');
        }
        $res = $this->official->freepublishStatus((string) $row->publish_id);
        $item = $res['article_detail'] ?? null;
        $status = (int) ($res['publish_status'] ?? -1);
        if ($status === 0) {
            $row->status = 3;
            $row->url = (string) ($res['article_url'] ?? ($item['url'] ?? ''));
            $row->save();
            return $this->success(['status' => 3, 'url' => $row->url], '发布成功');
        }
        if ($status === 1 || $status === 4) {
            return $this->success(['status' => $status], $status === 1 ? '发布中' : '审核中');
        }
        return $this->error('发布失败: ' . ($res['fail_idx'] ?? json_encode($res, JSON_UNESCAPED_UNICODE)));
    }

    /**
     * DELETE /wechat/publish/{id}
     */
    public function publishRemove(int $id): Response
    {
        if ($res = $this->guard()) {
            return $res;
        }
        $row = WechatPublish::find($id);
        if ($row !== null) {
            $row->delete();
        }
        return $this->success('删除成功');
    }
}
