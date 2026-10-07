<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\enum\Code;
use app\model\UserPref;
use support\Request;
use support\Response;

/**
 * 个人界面偏好（页面配置/主题/语言）：登录即可读写本人偏好，不过 purview
 */
class UserPrefController extends Controller
{
    /** 可同步键白名单：hideMenu 由前端响应式钩子驱动，禁止入库 */
    private const ALLOWED_BOOL = ['navbar', 'menu', 'topMenu', 'footer', 'tabBar', 'colorWeak', 'menuCollapse'];
    private const ALLOWED = ['theme', 'locale', 'menuWidth', ...self::ALLOWED_BOOL];

    private const MAX_BYTES = 4096;

    /**
     * GET /user/pref：本人偏好，无记录返回空对象
     */
    public function get(): Response
    {
        $row = UserPref::find($this->adminId);
        return $this->success($row?->prefs ?? (object) []);
    }

    /**
     * PUT /user/pref：整体保存（白名单过滤 + 类型归一 + 4KB 上限）
     */
    public function save(Request $request): Response
    {
        $prefs = $request->post('prefs');
        if (!is_array($prefs)) {
            return $this->error('参数错误', Code::PARAM_VALIDATE->value);
        }
        $filtered = [];
        foreach (self::ALLOWED as $key) {
            if (array_key_exists($key, $prefs)) {
                $filtered[$key] = $prefs[$key];
            }
        }
        foreach (self::ALLOWED_BOOL as $key) {
            if (array_key_exists($key, $filtered)) {
                $filtered[$key] = (bool) $filtered[$key];
            }
        }
        if (isset($filtered['menuWidth'])) {
            $filtered['menuWidth'] = max(160, min(500, (int) $filtered['menuWidth']));
        }
        if (isset($filtered['theme'])) {
            $filtered['theme'] = $filtered['theme'] === 'dark' ? 'dark' : 'light';
        }
        if (isset($filtered['locale'])) {
            $filtered['locale'] = in_array($filtered['locale'], ['zh-CN', 'en-US'], true) ? $filtered['locale'] : 'zh-CN';
        }
        if (strlen(json_encode($filtered, JSON_UNESCAPED_UNICODE)) > self::MAX_BYTES) {
            return $this->error('配置内容过大', Code::PARAM_VALIDATE->value);
        }
        UserPref::updateOrCreate(['admin_id' => $this->adminId], ['prefs' => $filtered]);
        return $this->success(['saved' => count($filtered)]);
    }
}
