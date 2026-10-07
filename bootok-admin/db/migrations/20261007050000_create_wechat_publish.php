<?php

use Phinx\Migration\AbstractMigration;

/**
 * 公众号：接入配置（配置中心「公众号」组）+ 发布记录映射表
 */
final class CreateWechatPublish extends AbstractMigration
{
    private const CONFIG_ITEMS = [
        // name, title, value, type, sort
        ['wechat_appid', '公众号 AppID', '', 'text', 80],
        ['wechat_appsecret', '公众号 AppSecret', '', 'text', 79],
        ['wechat_token', '服务器验证 Token', '', 'text', 78],
        ['wechat_welcome', '关注欢迎语', '欢迎关注！', 'text', 77],
    ];

    public function up(): void
    {
        foreach (self::CONFIG_ITEMS as [$name, $title, $value, $type, $sort]) {
            $this->execute(
                "INSERT INTO `sys_config` (`name`, `title`, `value`, `type`, `group`, `sort`)
                 SELECT '{$name}', '{$title}', '{$value}', '{$type}', '公众号', {$sort}
                 WHERE NOT EXISTS (SELECT 1 FROM `sys_config` WHERE `name` = '{$name}')"
            );
        }

        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_wechat_publish` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `article_id`     INT UNSIGNED NOT NULL COMMENT '文章',
    `title`          VARCHAR(200) NOT NULL DEFAULT '' COMMENT '标题快照',
    `draft_media_id` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '草稿 media_id',
    `publish_id`     VARCHAR(100) NOT NULL DEFAULT '' COMMENT '发布任务 publish_id',
    `url`            VARCHAR(500) NOT NULL DEFAULT '' COMMENT '发布后图文链接',
    `status`         TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0失败/1草稿/2已提交发布/3发布成功',
    `error`          VARCHAR(500) NOT NULL DEFAULT '',
    `created_at`     DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_article` (`article_id`)
) ENGINE = InnoDB COMMENT '公众号发布记录';
SQL);
    }

    public function down(): void
    {
        foreach (self::CONFIG_ITEMS as [$name, , ,]) {
            $this->execute("DELETE FROM `sys_config` WHERE `name` = '{$name}'");
        }
        $this->execute('DROP TABLE IF EXISTS `sys_wechat_publish`');
    }
}
