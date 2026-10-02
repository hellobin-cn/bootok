<?php

use Phinx\Migration\AbstractMigration;

/**
 * 文章演示软删除：deleted_at 列（模型启用 SoftDeletes + 字段级审计）
 */
final class ArticleSoftDeletes extends AbstractMigration
{
    public function up(): void
    {
        $this->table('article')
            ->addColumn('deleted_at', 'datetime', ['null' => true, 'after' => 'updated_at', 'comment' => '软删除时间'])
            ->addIndex(['deleted_at'])
            ->update();
    }

    public function down(): void
    {
        $this->table('article')->removeColumn('deleted_at')->update();
    }
}
