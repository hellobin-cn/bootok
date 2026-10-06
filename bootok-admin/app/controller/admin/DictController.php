<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\service\DictService;
use support\Request;
use support\Response;

/**
 * 数据字典端点（字典类型/项的 CRUD 走 /auto/dict-type、/auto/dict-item）
 */
class DictController extends Controller
{
    /**
     * 全部启用字典（code => 选项集），前端表格标签翻译可用
     * GET /dict/all
     */
    public function all(): Response
    {
        return $this->success(DictService::all());
    }
}
