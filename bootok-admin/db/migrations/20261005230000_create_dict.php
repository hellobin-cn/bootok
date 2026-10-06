<?php

use Phinx\Migration\AbstractMigration;

/**
 * 数据字典：可后台维护的枚举/选项集，供表单 DSL 的 DictSelect 消费
 */
final class CreateDict extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_dict_type` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`       VARCHAR(100) NOT NULL COMMENT '类型编码（模型 DictSelect 引用）',
    `name`       VARCHAR(100) NOT NULL COMMENT '类型名称',
    `remark`     VARCHAR(500) NULL COMMENT '备注',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE = InnoDB COMMENT '字典类型';

CREATE TABLE IF NOT EXISTS `sys_dict_item` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type_id`    INT UNSIGNED NOT NULL COMMENT '字典类型',
    `label`      VARCHAR(255) NOT NULL COMMENT '显示文本',
    `value`      VARCHAR(255) NOT NULL COMMENT '存储值',
    `sort`       INT NOT NULL DEFAULT 0 COMMENT '排序（升序）',
    `status`     TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1 启用 / 0 停用',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_type_value` (`type_id`, `value`),
    KEY `idx_type` (`type_id`)
) ENGINE = InnoDB COMMENT '字典项';

-- 预置常用字典：通用是/否
INSERT INTO `sys_dict_type` (`code`, `name`, `remark`) VALUES ('yes_no', '通用是/否', '通用布尔选项');
INSERT INTO `sys_dict_item` (`type_id`, `label`, `value`, `sort`)
SELECT `id`, '是', '1', 1 FROM `sys_dict_type` WHERE `code` = 'yes_no';
INSERT INTO `sys_dict_item` (`type_id`, `label`, `value`, `sort`)
SELECT `id`, '否', '0', 2 FROM `sys_dict_type` WHERE `code` = 'yes_no';
SQL);
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sys_dict_item`');
        $this->execute('DROP TABLE IF EXISTS `sys_dict_type`');
    }
}
