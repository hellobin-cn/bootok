<?php

use Phinx\Migration\AbstractMigration;

/**
 * Article 补归属列（数据权限载体）：创建人 + 部门
 * 存量文章归属回填至超管 admin_id=1
 */
final class AddArticleOwnership extends AbstractMigration
{
    public function up(): void
    {
        $this->execute("ALTER TABLE `article` ADD COLUMN `admin_id` INT UNSIGNED NOT NULL DEFAULT 1 COMMENT '创建人' AFTER `title`");
        $this->execute("ALTER TABLE `article` ADD COLUMN `dept_id` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '所属部门' AFTER `admin_id`");
    }

    public function down(): void
    {
        $this->execute("ALTER TABLE `article` DROP COLUMN `dept_id`");
        $this->execute("ALTER TABLE `article` DROP COLUMN `admin_id`");
    }
}
