<?php

namespace app\controller;

use app\storage\Storage;
use support\Request;
use support\Response;

class UploadController extends Controller
{
    /** 允许的扩展名（不含svg：可内嵌脚本，同源托管构成存储型XSS） */
    private const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'mp4', 'mp3'];

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
        $relative = 'upload/' . date('Ym') . '/' . date('Ymd_His') . '_' . bin2hex(random_bytes(6)) . '.' . $extension;
        try {
            return Storage::driver()->put($file, $relative);
        } catch (\Throwable $e) {
            return '[' . $e->getMessage() . ']';
        }
    }
}
