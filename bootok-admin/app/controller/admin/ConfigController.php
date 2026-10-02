<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\enum\Code;
use app\model\SystemConfig;
use app\service\ConfigService;
use support\Request;
use support\Response;

/**
 * 参数设置（配置中心编辑侧）
 * settings 下发分组+类型化值；save 批量保存
 * 与 /auto/system-config（配置项管理）分工：这里调值，那里增删配置项
 */
class ConfigController extends Controller
{
    /**
     * 全部配置项（按分组，值已按类型 cast）
     */
    public function settings(): Response
    {
        $rows = SystemConfig::query()
            ->orderByDesc('sort')
            ->get(['config_id', 'name', 'title', 'value', 'type', 'group']);
        $data = $rows->map(fn($row) => [
            'config_id' => $row->config_id,
            'name'      => $row->name,
            'title'     => $row->title,
            'group'     => $row->group,
            'type'      => $row->type,
            'value'     => ConfigService::cast($row->type, $row->value),
        ])->values()->toArray();
        return $this->success($data);
    }

    /**
     * 批量保存：{ items: { name: value, ... } }
     */
    public function save(Request $request): Response
    {
        $items = $request->post('items');
        if (!is_array($items) || !$items) {
            return $this->error('没有需要保存的配置', Code::PARAM_VALIDATE->value);
        }
        $saved = 0;
        foreach ($items as $name => $value) {
            if (ConfigService::set((string)$name, $value)) {
                $saved++;
            }
        }
        return $this->success(['saved' => $saved], "已保存 {$saved} 项配置");
    }
}
