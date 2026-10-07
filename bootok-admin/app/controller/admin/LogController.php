<?php

namespace app\controller\admin;

use app\controller\Controller;
use app\enum\Code;
use support\Request;
use support\Response;
use Throwable;

/**
 * 运行日志查看（仅超管）：读取 runtime/logs 下日志文件的尾部
 * 文件名白名单 + realpath 约束防目录穿越；单文件超 10MB 只读末尾 2MB
 */
class LogController extends Controller
{
    private const MAX_LINES = 500;
    private const BIG_FILE = 10485760; // 10MB
    private const TAIL_WINDOW = 2097152; // 2MB

    /**
     * GET /log/files：日志文件清单（新修改在前）
     */
    public function files(): Response
    {
        if (!$this->request->getCustomData('isSuper', 0)) {
            return $this->error('仅超级管理员可查看日志', Code::FORBIDDEN->value);
        }
        $files = [];
        foreach (glob(runtime_path('logs') . '/*.log') ?: [] as $path) {
            $files[] = [
                'name'  => basename($path),
                'size'  => filesize($path) ?: 0,
                'mtime' => filemtime($path) ?: 0,
            ];
        }
        usort($files, fn($a, $b) => $b['mtime'] <=> $a['mtime']);
        return $this->success($files);
    }

    /**
     * GET /log/tail?file=&lines=&keyword=
     */
    public function tail(Request $request): Response
    {
        if (!$this->request->getCustomData('isSuper', 0)) {
            return $this->error('仅超级管理员可查看日志', Code::FORBIDDEN->value);
        }
        $name = (string) $request->input('file', '');
        if (!preg_match('/^[a-zA-Z0-9._-]+\.log$/', $name)) {
            return $this->error('非法文件名', Code::PARAM_VALIDATE->value);
        }
        $path = runtime_path('logs') . DIRECTORY_SEPARATOR . $name;
        $real = realpath($path);
        if ($real === false || strpos($real, realpath(runtime_path('logs'))) !== 0) {
            return $this->error('文件不存在', Code::PARAM_VALIDATE->value);
        }
        $lines = max(1, min(self::MAX_LINES, (int) $request->input('lines', 200)));
        $keyword = trim((string) $request->input('keyword', ''));

        $rows = [];
        try {
            $fp = fopen($real, 'rb');
            if ($fp === false) {
                return $this->error('文件无法读取');
            }
            $size = filesize($real) ?: 0;
            if ($size > self::BIG_FILE) {
                fseek($fp, -self::TAIL_WINDOW, SEEK_END);
                fgets($fp); // 丢弃半行
            }
            $buffer = '';
            while (!feof($fp)) {
                $buffer .= fread($fp, 65536);
            }
            fclose($fp);
            $all = preg_split('/\r?\n/', $buffer) ?: [];
            foreach (array_reverse($all) as $line) {
                if ($line === '') {
                    continue;
                }
                if ($keyword !== '' && !str_contains($line, $keyword)) {
                    continue;
                }
                $rows[] = $this->parseLine($name, $line);
                if (count($rows) >= $lines) {
                    break;
                }
            }
        } catch (Throwable $e) {
            return $this->error('读取失败：' . mb_substr($e->getMessage(), 0, 100));
        }
        return $this->success($rows);
    }

    /**
     * 兼容两种行格式：Monolog JSON 行 与 webman 文本行
     */
    private function parseLine(string $file, string $line): array
    {
        $row = ['time' => '', 'level' => 'INFO', 'channel' => '', 'message' => mb_substr($line, 0, 500)];
        if (str_starts_with($file, 'json-')) {
            $json = json_decode($line, true);
            if (is_array($json)) {
                $row['time'] = (string) ($json['datetime'] ?? '');
                $row['level'] = (string) ($json['level_name'] ?? 'INFO');
                $row['channel'] = (string) ($json['channel'] ?? '');
                $context = $json['context'] ?? [];
                $extra = $json['extra'] ?? [];
                $suffix = ($context || $extra) ? ' ' . json_encode($context ?: $extra, JSON_UNESCAPED_UNICODE) : '';
                $row['message'] = mb_substr((string) ($json['message'] ?? '') . $suffix, 0, 500);
            }
            return $row;
        }
        if (preg_match('/^\[([^\]]+)\]\s+(\S+)\.(\w+):\s*(.*)$/s', $line, $m)) {
            $row['time'] = $m[1];
            $row['channel'] = $m[2];
            $row['level'] = $m[3];
            $row['message'] = mb_substr($m[4], 0, 500);
        }
        return $row;
    }
}
