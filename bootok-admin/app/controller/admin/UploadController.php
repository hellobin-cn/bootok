<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\service\storage\ChannelManager;
use DI\Attribute\Inject;
use support\Request;
use support\Response;

class UploadController extends Controller
{
    /** 存储驱动经容器注入（默认渠道），业务代码不写死具体实现 */
    #[Inject]
    private ChannelManager $channels;

    /** 允许的扩展名（不含svg：可内嵌脚本，同源托管构成存储型XSS） */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'mp4', 'mp3', 'txt', 'csv', 'md'];

    public function upload(Request $request): Response
    {
        $url = $this->store($request);
        if (is_string($url) && str_starts_with($url, '[')) {
            return $this->error(substr($url, 1, -1));
        }
        return $this->success(['file_url' => $url, 'remark' => $request->post('remark')]);
    }

    /**
     * 编辑器上传，wangeditor响应协议
     */
    public function editorUpload(Request $request): Response
    {
        $url = $this->store($request);
        if (is_string($url) && str_starts_with($url, '[')) {
            return json(['errno' => 1, 'message' => substr($url, 1, -1)]);
        }
        return json(['errno' => 0, 'data' => ['url' => $url]]);
    }

    /**
     * 保存上传文件，成功返回url，失败返回 [错误信息] 包装的字符串
     */
    private function store(Request $request): string
    {
        $file = $request->file('file');
        if (!$file || !$file->isValid()) {
            return '[请选择文件]';
        }
        $extension = strtolower($file->getUploadExtension());
        if (!in_array($extension, self::ALLOWED_EXTENSIONS)) {
            return '[不支持的文件类型]';
        }

        // 目标目录：默认按月目录；文件管理器上传时指定渠道与目录（物理路径随目录）
        $channelId = (int) $request->post('channel_id', 0);
        $folderId = $request->post('folder_id') !== null ? (int) $request->post('folder_id') : null;
        try {
            if ($channelId > 0) {
                $channel = \app\model\StorageChannel::find($channelId);
                if ($channel === null) {
                    return '[存储渠道不存在]';
                }
                $storage = $this->channels->forChannel($channel);
                $dirPath = \support\Container::get(\app\service\storage\FolderService::class)->folderPath($channelId, $folderId);
                $relative = ($dirPath === '' ? '' : $dirPath . '/') . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
            } else {
                $storage = $this->channels->default();
                $relative = 'upload/' . date('Ym') . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
            }

            // 元信息必须在 move 之前读取（move 后临时文件不存在）
            $uploadName = (string)$file->getUploadName();
            $size = (int)$file->getSize();
            $storage->put($file, $relative);
            $url = $storage->url($relative);
            $this->recordAttachment($channelId > 0 ? $channelId : $this->channels->defaultChannel()->id, $folderId, $relative, $uploadName, $size, $extension);
            return $url;
        } catch (\Throwable $e) {
            return '[' . $e->getMessage() . ']';
        }
    }

    /**
     * 附件落库（数据资产化：谁传的/何时/多大/存于哪个渠道与目录）；失败不阻断上传
     */
    private function recordAttachment(int $channelId, ?int $folderId, string $relative, string $uploadName, int $size, string $extension): void
    {
        try {
            \app\model\Attachment::create([
                'admin_id'      => $this->adminId,
                'admin_name'    => $this->adminName,
                'channel_id'    => $channelId,
                'folder_id'     => $folderId,
                'path'          => '/' . $relative,
                'original_name' => mb_substr($uploadName, 0, 255),
                'size'          => $size,
                'ext'           => $extension,
            ]);
        } catch (\Throwable $e) {
            \support\Log::error('附件落库失败: ' . $e->getMessage());
        }
    }
}
