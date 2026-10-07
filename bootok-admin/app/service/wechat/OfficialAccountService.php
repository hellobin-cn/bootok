<?php

namespace app\service\wechat;

use app\service\ConfigService;
use app\service\MessageService;
use support\Log;

/**
 * 公众号（认证服务号）：接入验证/事件回复 + 模板消息 + JS-SDK + 二维码 + 菜单 + 草稿发布
 * 事件处理从 zcy 移植，业务落点改为 bootok（欢迎语配置化 + 超管消息通知）
 */
class OfficialAccountService extends WechatService
{
    private string $token = '';

    public function __construct()
    {
        parent::__construct();
        $this->token = (string) (ConfigService::group('公众号')['wechat_token'] ?? '');
    }

    // ==================== 接入与消息 ====================

    public function verifySignature(string $signature, string $timestamp, string $nonce): bool
    {
        if ($this->token === '') {
            Log::warning('公众号回调签名校验跳过：wechat_token 未配置或读取为空');
            return false;
        }
        $arr = [$this->token, $timestamp, $nonce];
        sort($arr, SORT_STRING);
        return sha1(implode($arr)) === $signature;
    }

    /** 事件/消息分发，返回被动回复 XML（可为空串） */
    public function parseMessage(string $xml): string
    {
        if ($xml === '') {
            return '';
        }
        $xml = str_replace(['<![CDATA[', ']]>'], ['', ''], $xml);
        $data = json_decode(json_encode(simplexml_load_string($xml) ?: []), true);
        if (!is_array($data) || !isset($data['MsgType'])) {
            return '';
        }
        $handler = strtolower((string) $data['MsgType']);
        $res = method_exists($this, $handler) ? $this->{$handler}($data) : '';
        return $this->textReply($data, (string) $res);
    }

    private function text(array $message): string
    {
        // v1：文本消息原样记录（自动问答按需扩展）
        Log::info('公众号文本消息', ['from' => $message['FromUserName'] ?? '', 'content' => $message['Content'] ?? '']);
        return (string) ConfigService::group('公众号')['wechat_welcome'] ?? '';
    }

    private function event(array $message): string
    {
        $event = strtolower((string) ($message['Event'] ?? ''));
        return match ($event) {
            'subscribe'   => $this->onSubscribe($message),
            'unsubscribe' => $this->onUnsubscribe($message),
            'scan', 'click', 'view' => '',
            default       => '',
        };
    }

    private function onSubscribe(array $message): string
    {
        $openid = (string) ($message['FromUserName'] ?? '');
        Log::info('公众号新关注', ['openid' => $openid]);
        (new MessageService())->sendToSupers('公众号新关注', "openid: {$openid}", MessageService::TYPE_NOTICE);
        return (string) (ConfigService::group('公众号')['wechat_welcome'] ?? '');
    }

    private function onUnsubscribe(array $message): string
    {
        $openid = (string) ($message['FromUserName'] ?? '');
        Log::info('公众号取消关注', ['openid' => $openid]);
        (new MessageService())->sendToSupers('公众号取关', "openid: {$openid}", MessageService::TYPE_NOTICE);
        return '';
    }

    /** 被动回复文本 */
    private function textReply(array $message, string $content): string
    {
        $createTime = time();
        $to = $message['FromUserName'] ?? '';
        $from = $message['ToUserName'] ?? '';
        return <<<XML
<xml>
  <ToUserName><![CDATA[{$to}]]></ToUserName>
  <FromUserName><![CDATA[{$from}]]></FromUserName>
  <CreateTime>{$createTime}</CreateTime>
  <MsgType><![CDATA[text]]></MsgType>
  <Content><![CDATA[{$content}]]></Content>
</xml>
XML;
    }

    // ==================== 模板消息 / JS-SDK / 二维码（zcy 移植） ====================

    public function sendMessage(string $openid, string $templateId, array $data, string $url = '', array $miniprogram = []): bool
    {
        $res = $this->request('post', '/cgi-bin/message/template/send', [
            'touser'      => $openid,
            'template_id' => $templateId,
            'url'         => $url,
            'miniprogram' => $miniprogram,
            'data'        => $data,
        ]);
        if ((int) ($res['errcode'] ?? 0) !== 0) {
            Log::error('发送公众号模板消息失败', $res);
            return false;
        }
        return true;
    }

    public function getTicket(): string
    {
        $cached = \support\Redis::connection()->get('wechat:jsapi-ticket-' . $this->appid);
        if ($cached) {
            return (string) $cached;
        }
        $res = $this->request('get', '/cgi-bin/ticket/getticket?type=jsapi');
        $ticket = (string) ($res['ticket'] ?? '');
        if ($ticket !== '') {
            \support\Redis::connection()->setex('wechat:jsapi-ticket-' . $this->appid, 7000, $ticket);
        }
        return $ticket;
    }

    public function getConfig(string $url): array
    {
        $params = [
            'noncestr'     => bin2hex(random_bytes(8)),
            'jsapi_ticket' => $this->getTicket(),
            'timestamp'    => time(),
            'url'          => $url,
        ];
        ksort($params);
        return [
            'appId'     => $this->appid,
            'timestamp' => $params['timestamp'],
            'nonceStr'  => $params['noncestr'],
            'signature' => hash('sha1', urldecode(http_build_query($params))),
        ];
    }

    public function getUserInfo(string $openid): ?array
    {
        $res = $this->request('get', '/cgi-bin/user/info?openid=' . urlencode($openid) . '&lang=zh_CN');
        if (isset($res['errcode'])) {
            return null;
        }
        return ['openid' => $res['openid'] ?? '', 'union_id' => $res['unionid'] ?? ''];
    }

    public function getQRCode(string $scene, string $actionName = 'QR_STR_SCENE', int $expireSeconds = 3600): ?string
    {
        $res = $this->request('post', '/cgi-bin/qrcode/create', [
            'action_name'    => $actionName,
            'expire_seconds' => $expireSeconds,
            'action_info'    => ['scene' => ['scene_str' => $scene]],
        ]);
        if (!isset($res['ticket'])) {
            return null;
        }
        return 'https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket=' . urlencode((string) $res['ticket']);
    }

    // ==================== 自定义菜单 ====================

    public function menuCreate(array $buttons): array
    {
        return $this->request('post', '/cgi-bin/menu/create', ['button' => $buttons]);
    }

    public function menuGet(): array
    {
        return $this->request('get', '/cgi-bin/get_current_selfmenu_info');
    }

    public function menuDelete(): array
    {
        return $this->request('get', '/cgi-bin/menu/delete');
    }

    // ==================== 发布链：素材 / 草稿 / 发布 ====================

    /**
     * 正文图片转存：下载原图 → 传微信素材库（仅认证号可用），返回微信域 URL
     */
    public function uploadContentImage(string $imageUrl): string
    {
        $bytes = $this->download($imageUrl);
        if ($bytes === null) {
            throw new \RuntimeException("图片下载失败: {$imageUrl}");
        }
        $res = $this->upload('/cgi-bin/media/uploadimg', 'media', $bytes, 'img.' . $this->guessExt($imageUrl, $bytes));
        if (empty($res['url'])) {
            throw new \RuntimeException('正文图片转存失败: ' . json_encode($res, JSON_UNESCAPED_UNICODE));
        }
        return (string) $res['url'];
    }

    /**
     * 封面缩略图：上传为永久素材，返回 thumb_media_id
     */
    public function uploadThumb(string $imageUrl): string
    {
        $bytes = $this->download($imageUrl);
        if ($bytes === null) {
            throw new \RuntimeException("封面下载失败: {$imageUrl}");
        }
        $filename = 'cover.' . $this->guessExt($imageUrl, $bytes);
        $res = $this->upload('/cgi-bin/material/upload_material', 'media', $bytes, $filename, ['type' => 'image']);
        if (empty($res['media_id'])) {
            throw new \RuntimeException('封面永久素材上传失败: ' . json_encode($res, JSON_UNESCAPED_UNICODE));
        }
        return (string) $res['media_id'];
    }

    /**
     * 新建草稿（articles 结构见微信文档；thumb_media_id 必填）
     */
    public function addDraft(array $articles): string
    {
        $res = $this->request('post', '/cgi-bin/draft/add', ['articles' => $articles]);
        if (empty($res['media_id'])) {
            throw new \RuntimeException('创建草稿失败: ' . json_encode($res, JSON_UNESCAPED_UNICODE));
        }
        return (string) $res['media_id'];
    }

    public function draftDelete(string $mediaId): array
    {
        return $this->request('post', '/cgi-bin/draft/delete', ['media_id' => $mediaId]);
    }

    /** 提交发布（推送给全部粉丝，谨慎调用），返回 publish_id */
    public function freepublishSubmit(string $mediaId): string
    {
        $res = $this->request('post', '/cgi-bin/freepublish/submit', ['media_id' => $mediaId]);
        if (empty($res['publish_id'])) {
            throw new \RuntimeException('提交发布失败: ' . json_encode($res, JSON_UNESCAPED_UNICODE));
        }
        return (string) $res['publish_id'];
    }

    /** 发布任务状态（publish_status: 0成功 1发布中 2原创失败 3普通失败 4审核中） */
    public function freepublishStatus(string $publishId): array
    {
        return $this->request('post', '/cgi-bin/freepublish/get', ['publish_id' => $publishId]);
    }

    private function guessExt(string $url, string $bytes): string
    {
        if (preg_match('/\.(jpe?g|png|gif|webp)(\?|$)/i', $url, $m)) {
            return strtolower($m[1] === 'jpeg' ? 'jpg' : $m[1]);
        }
        $magic = substr($bytes, 0, 3);
        return $magic === "\xFF\xD8\xFF" ? 'jpg' : ($magic === "\x89PNG" ? 'png' : 'jpg');
    }
}
