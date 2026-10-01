-- bootok-admin 通用后台 建表脚本与种子数据
-- 权限模型：前端路由全静态定义，sys_group.purview 存放路由name与按钮权限点集合
-- 默认超级管理员：admin / admin123；演示权限组账号：editor / editor123

CREATE DATABASE IF NOT EXISTS `bootok` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `bootok`;

-- 管理员
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

-- 权限组（purview = 前端路由name + 按钮权限点 集合）
CREATE TABLE IF NOT EXISTS `sys_group` (
    `group_id`   INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `group_name` VARCHAR(50) NOT NULL COMMENT '名称',
    `purview`    TEXT COMMENT '权限集合JSON数组，如 ["System","SystemAdmin","article.create"]',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`group_id`)
) ENGINE = InnoDB COMMENT '权限组';

-- 操作日志
CREATE TABLE IF NOT EXISTS `sys_operation_log` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `admin_id`   INT UNSIGNED NOT NULL DEFAULT 0,
    `admin_name` VARCHAR(50)  NOT NULL DEFAULT '',
    `method`     VARCHAR(10)  NOT NULL DEFAULT '',
    `path`       VARCHAR(255) NOT NULL DEFAULT '',
    `ip`         VARCHAR(45)  NOT NULL DEFAULT '',
    `params`     TEXT COMMENT '请求参数JSON，敏感字段已脱敏',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_admin_id` (`admin_id`)
) ENGINE = InnoDB COMMENT '操作日志';

-- 系统配置
CREATE TABLE IF NOT EXISTS `sys_config` (
    `config_id`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100) NOT NULL COMMENT '配置键',
    `title`      VARCHAR(50)  NOT NULL COMMENT '配置名',
    `value`      TEXT COMMENT '配置值',
    `sort`       INT          NOT NULL DEFAULT 0,
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`config_id`),
    UNIQUE KEY `uk_name` (`name`)
) ENGINE = InnoDB COMMENT '系统配置';

-- 文章（业务模块示例表）
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

-- ---------------- 种子数据 ----------------

INSERT INTO `sys_admin` (`admin_id`, `admin_name`, `admin_password`, `group_id`, `is_super`) VALUES
(1, 'admin', '$2y$12$owN6ykmXj98HmLTl54Lc7u3OSXzwZK3KXuHtxw7ojRklqAD9pzoEe', 1, 1),
(2, 'editor', '$2y$12$UmYMJJELLHdG1aSc4EEwr.TmdAZCjKjuKGVczYgq/SgeHytTvQqYK', 2, 0);

INSERT INTO `sys_group` (`group_id`, `group_name`, `purview`) VALUES
(1, '超级管理员', '[]'),
(2, '内容编辑', '["Content","ContentArticle","article.create","article.edit"]');

INSERT INTO `sys_config` (`name`, `title`, `value`, `sort`) VALUES
('site_name', '站点名称', 'Bootok Admin', 100),
('site_icp', '备案号', '', 90);

INSERT INTO `article` (`title`, `cover`, `content`, `status`, `sort`) VALUES
('欢迎使用 bootok-admin', '', '<p>这是一个通用后台管理示例文章，整套后台由模型注解与表单DSL驱动。</p>', 1, 100),
('第二个示例文章', '', '<p>新增业务模块只需：建表 + 写一个带 #[AutoResource] 注解的模型。</p>', 1, 90);
