<?php

namespace app\controller\client;

use app\model\Article;
use support\Request;
use support\Response;

/**
 * 用户端控制器
 * 与管理端共用模型，但接口手动定义、字段显式白名单，绝不复用 /auto 自动资源
 */
class ClientController
{
    public function ping(): Response
    {
        return json_success('', [
            'name' => config('app.name'),
            'role' => 'client',
            'time' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * 文章列表示例：只暴露客户端所需字段，只返回上架数据
     */
    public function articles(Request $request): Response
    {
        $page = max(1, (int)$request->get('page', 1));
        $pageSize = min(50, max(1, (int)$request->get('page_size', 10)));

        $query = Article::where('status', 1);
        $total = (clone $query)->count();
        $list = $query->orderByDesc('sort')
            ->forPage($page, $pageSize)
            ->get(['article_id', 'title', 'cover', 'created_at'])
            ->toArray();

        return json_success('', [
            'list'      => $list,
            'page'      => $page,
            'page_size' => $pageSize,
            'total'     => $total,
        ]);
    }
}
