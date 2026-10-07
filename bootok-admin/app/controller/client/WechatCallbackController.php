<?php

namespace app\controller\client;

use app\service\wechat\OfficialAccountService;
use support\Request;
use support\Response;

/**
 * 公众号服务器回调（client 端口，公网可达）：接入验证 + 事件/消息接收
 * 在公众平台「服务器配置」填 本域名/client/v1/wechat/callback（明文模式）
 */
class WechatCallbackController
{
    public function __construct(private readonly OfficialAccountService $official)
    {
    }

    public function callback(Request $request): Response
    {
        if (!$this->official->verifySignature(
            (string) $request->get('signature', ''),
            (string) $request->get('timestamp', ''),
            (string) $request->get('nonce', '')
        )) {
            return new Response(403);
        }
        // 接入验证：原样回显 echostr
        if ($request->method() === 'GET') {
            return new Response(200, [], (string) $request->get('echostr', ''));
        }
        // 事件/消息：返回被动回复 XML（空串 = 不回复）
        return new Response(200, [], $this->official->parseMessage((string) $request->rawBody()));
    }
}
