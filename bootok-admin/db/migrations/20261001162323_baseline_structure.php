<?php

use Phinx\Migration\AbstractMigration;

/**
 * 基线结构：bootok 全部系统表
 * 已有库执行时幂等（IF NOT EXISTS）；全新库一键建齐
 */
final class BaselineStructure extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_admin` (
    `admin_id`       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_name`     VARCHAR(50)  NOT NULL COMMENT '用户名',
    `admin_password` VARCHAR(255) NOT NULL COMMENT '密码散列',
    `group_id`       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '权限组ID',
    `is_super`       TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '是否超级管理员',
    `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`admin_id`),
    UNIQUE KEY `uk_admin_name` (`admin_name`)
) ENGINE = InnoDB COMMENT '管理员';

CREATE TABLE IF NOT EXISTS `sys_group` (
    `group_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_name` VARCHAR(50) NOT NULL COMMENT '名称',
    `purview`    TEXT COMMENT '权限集合JSON：["路由name","权限点"]',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`group_id`)
) ENGINE = InnoDB COMMENT '权限组';

CREATE TABLE IF NOT EXISTS `sys_operation_log` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`   INT UNSIGNED NOT NULL DEFAULT 0,
    `admin_name` VARCHAR(50)  NOT NULL DEFAULT '',
    `method`     VARCHAR(10)  NOT NULL DEFAULT '',
    `path`       VARCHAR(255) NOT NULL DEFAULT '',
    `ip`         VARCHAR(45)  NOT NULL DEFAULT '',
    `params`     TEXT COMMENT '请求参数JSON，敏感字段脱敏',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_id` (`admin_id`)
) ENGINE = InnoDB COMMENT '管理端操作日志';

CREATE TABLE IF NOT EXISTS `sys_config` (
    `config_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL COMMENT '配置键',
    `title`      VARCHAR(50)  NOT NULL COMMENT '配置名',
    `value`      TEXT COMMENT '配置值',
    `sort`       INT NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`config_id`),
    UNIQUE KEY `uk_name` (`name`)
) ENGINE = InnoDB COMMENT '系统配置';

CREATE TABLE IF NOT EXISTS `article` (
    `article_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(100) NOT NULL COMMENT '标题',
    `cover`      VARCHAR(500) NOT NULL DEFAULT '' COMMENT '封面图',
    `content`    MEDIUMTEXT COMMENT '内容',
    `status`     TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1上架 0下架',
    `sort`       INT NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`article_id`)
) ENGINE = InnoDB COMMENT '文章';
SQL);
    }

    public function down(): void
    {
        foreach (['article', 'sys_config', 'sys_operation_log', 'sys_group', 'sys_admin'] as $table) {
            $this->execute("DROP TABLE IF EXISTS `$table`");
        }
    }
}
