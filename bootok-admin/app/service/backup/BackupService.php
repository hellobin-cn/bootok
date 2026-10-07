<?php

namespace app\service\backup;

use app\model\Backup;
use app\service\ConfigService;
use app\service\storage\StorageInterface;
use Throwable;

/**
 * 数据库备份：纯 PHP 全量导出（gzip 流式，无 mysqldump 依赖，Windows/Linux 通用）
 *
 * 语句以每份备份随机的标记行分隔（restore 只认自家格式），推送到默认存储渠道；
 * 超大库（千万行级）建议使用外部物理备份，本方案面向底座常规数据量。
 */
class BackupService
{
    private const TEMP_DIR = 'runtime/backup';

    public function __construct(private readonly StorageInterface $storage)
    {
    }

    /**
     * 执行一次备份：dump → gzip → 推存储 → 记录（失败也落记录）→ 保留策略清理
     */
    public function run(string $trigger): Backup
    {
        $started = microtime(true);
        $filename = 'backup_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.sql.gz';
        $row = Backup::create([
            'filename' => $filename,
            'trigger'  => $trigger === 'task' ? 'task' : 'manual',
            'status'   => 0,
        ]);
        try {
            // 标记必须是无空格单 token：首行头部声明，恢复端按 \S+ 提取
            $marker = '--bootok-stmt:' . bin2hex(random_bytes(6));
            $content = $this->dump($marker);
            $object = 'backup/' . $filename;
            $this->storage->putContent($object, gzencode($content, 6));
            $row->object = $object;
            $row->size = strlen(gzencode($content, 6));
            $row->tables = (int) (new \PDO(...$this->dsn()))
                ->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE'")
                ->fetchColumn();
            $row->status = 1;
            $row->duration_ms = (int) ((microtime(true) - $started) * 1000);
            $row->save();
            $this->retention();
        } catch (Throwable $e) {
            $row->status = 0;
            $row->error = mb_substr($e->getMessage(), 0, 500);
            $row->duration_ms = (int) ((microtime(true) - $started) * 1000);
            $row->save();
            throw $e;
        }
        return $row;
    }

    /**
     * 恢复：从存储渠道取回 → 解压 → 逐语句执行（DDL 不可事务，恢复期间数据库短暂处于中间态）
     *
     * @return array{statements: int, ms: int}
     */
    public function restore(Backup $backup): array
    {
        if ((int) $backup->status !== 1 || $backup->object === '') {
            throw new \RuntimeException('该备份不可恢复（失败记录或缺文件）');
        }
        $raw = gzdecode((string) $this->storage->get($backup->object));
        // 标记行在首行头部声明
        if (!preg_match('/^-- bootok-backup v1 marker=(\S+)\n/', $raw, $m)) {
            throw new \RuntimeException('备份文件格式无法识别');
        }
        $marker = $m[1];
        $started = microtime(true);
        $pdo = new \PDO(...$this->dsn());
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $count = 0;
        foreach (explode("\n" . $marker . "\n", $raw) as $chunk) {
            $chunk = trim($chunk);
            if ($chunk === '' || str_starts_with($chunk, '--')) {
                continue;
            }
            $pdo->exec($chunk);
            $count++;
        }
        return ['statements' => $count, 'ms' => (int) ((microtime(true) - $started) * 1000)];
    }

    public function content(Backup $backup): string
    {
        if ((int) $backup->status !== 1 || $backup->object === '') {
            throw new \RuntimeException('失败记录没有可下载的文件');
        }
        $raw = $this->storage->get($backup->object);
        if ($raw === null) {
            throw new \RuntimeException('存储渠道中找不到该备份文件');
        }
        return $raw;
    }

    public function remove(Backup $backup): void
    {
        if ($backup->object !== '') {
            $this->storage->delete($backup->object);
        }
        $backup->delete();
    }

    /**
     * 全量导出到字符串：结构 + 数据（标记行分隔语句）
     */
    private function dump(string $marker): string
    {
        $pdo = new \PDO(...$this->dsn());
        // PHP 8.5：PDO::MYSQL_ATTR_* 已弃用，统一走 Pdo\Mysql 驱动常量
        $pdo->setAttribute(\Pdo\Mysql::ATTR_USE_BUFFERED_QUERY, false);
        $out = "-- bootok-backup v1 marker={$marker}\n";
        $out .= "-- db={$this->dbname()} at=" . date('Y-m-d H:i:s') . "\n";
        $out .= "SET NAMES utf8mb4;\n{$marker}\n";
        $out .= "SET FOREIGN_KEY_CHECKS=0;\n{$marker}\n";

        $tables = $pdo->query("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' AND TABLE_NAME <> 'sys_backup' ORDER BY TABLE_NAME")
            ->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            $safe = '`' . str_replace('`', '``', (string) $table) . '`';
            $create = $pdo->query("SHOW CREATE TABLE {$safe}")->fetch(\PDO::FETCH_NUM)[1] ?? null;
            if ($create === null) {
                continue;
            }
            $out .= "DROP TABLE IF EXISTS {$safe};\n{$marker}\n";
            $out .= $create . ";\n{$marker}\n";
            $stmt = $pdo->query("SELECT * FROM {$safe}");
            $batch = [];
            while (($row = $stmt->fetch(\PDO::FETCH_NUM)) !== false) {
                $values = array_map(fn($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), $row);
                $batch[] = '(' . implode(',', $values) . ')';
                if (count($batch) >= 100) {
                    $out .= "INSERT INTO {$safe} VALUES " . implode(',', $batch) . ";\n{$marker}\n";
                    $batch = [];
                }
            }
            if ($batch) {
                $out .= "INSERT INTO {$safe} VALUES " . implode(',', $batch) . ";\n{$marker}\n";
            }
            $stmt->closeCursor();
        }
        $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
        return $out;
    }

    /**
     * 保留策略：成功备份超过 backup_keep 份时，从最旧开始删记录与对象
     */
    private function retention(): void
    {
        $keep = max(1, (int) ConfigService::get('backup_keep', 7));
        $ids = Backup::where('status', 1)->orderBy('backup_id')->pluck('backup_id')->all();
        $stale = array_slice($ids, 0, max(0, count($ids) - $keep));
        foreach ($stale as $id) {
            $row = Backup::find($id);
            if ($row !== null) {
                $this->remove($row);
            }
        }
    }

    private function dsn(): array
    {
        $env = parse_ini_file(base_path() . '/.env');
        return [
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $env['DB_HOST'], $env['DB_PORT'] ?? 3306, $env['DB_DATABASE']),
            (string) $env['DB_USERNAME'],
            (string) $env['DB_PASSWORD'],
        ];
    }

    private function dbname(): string
    {
        $env = parse_ini_file(base_path() . '/.env');
        return (string) $env['DB_DATABASE'];
    }
}
