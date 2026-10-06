<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\enum\Code;
use app\model\Message;
use app\service\MessageService;
use support\Response;

/**
 * 站内消息（个人数据）：全部限定 receiver_admin_id=当前登录人，登录态即可，不走 purview
 */
class MessageController extends Controller
{
    public function __construct(private readonly MessageService $messages)
    {
        parent::__construct();
    }

    /**
     * 铃铛轮询：未读数 + 最近 8 条
     * GET /message/latest
     */
    public function latest(): Response
    {
        $items = Message::where('receiver_admin_id', $this->adminId)
            ->orderBy('message_id', 'desc')
            ->limit(8)
            ->get();
        return $this->success([
            'unread' => $this->messages->unreadCount($this->adminId),
            'items'  => $items,
        ]);
    }

    /**
     * 我的消息分页
     * GET /message/rows?page=&page_size=
     */
    public function rows(): Response
    {
        $pageSize = max(1, min(100, (int) $this->request->input('page_size', 15)));
        $query = Message::where('receiver_admin_id', $this->adminId);
        if (($isRead = $this->request->input('is_read')) !== null && $isRead !== '') {
            $query->where('is_read', (int) $isRead);
        }
        $paginator = $query->orderBy('message_id', 'desc')
            ->paginate($pageSize, ['*'], 'page', (int) $this->request->input('page', 1));
        return $this->success([
            'items'  => $paginator->items(),
            'total'  => $paginator->total(),
            'unread' => $this->messages->unreadCount($this->adminId),
        ]);
    }

    /**
     * 批量标已读（仅自己的消息）
     * POST /message/read {ids: []}
     */
    public function read(): Response
    {
        $ids = array_slice(array_map('intval', (array) $this->request->post('ids', [])), 0, 200);
        if (!$ids) {
            return $this->error('请选择要标记的消息', Code::PARAM_VALIDATE->value);
        }
        $read = Message::where('receiver_admin_id', $this->adminId)
            ->whereIn('message_id', $ids)
            ->where('is_read', 0)
            ->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
        return $this->success(['read' => $read]);
    }

    /**
     * 全部已读
     * POST /message/read-all
     */
    public function readAll(): Response
    {
        $read = Message::where('receiver_admin_id', $this->adminId)
            ->where('is_read', 0)
            ->update(['is_read' => 1, 'read_at' => date('Y-m-d H:i:s')]);
        return $this->success(['read' => $read]);
    }

    /**
     * 删除自己的消息
     * DELETE /message/{id}
     */
    public function remove(int $id): Response
    {
        Message::where('receiver_admin_id', $this->adminId)->where('message_id', $id)->delete();
        return $this->success('删除成功');
    }
}
