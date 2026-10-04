<?php

use Phinx\Migration\AbstractMigration;

/**
 * 邮件体系：SMTP 渠道（凭据界面可管理）+ 邮件模板（DB 存储，界面编辑预览）+ 发送记录（结果与内容快照）
 */
final class CreateMail extends AbstractMigration
{
    public function up(): void
    {
        $this->execute(<<<'SQL'
CREATE TABLE IF NOT EXISTS `sys_mail_channel` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `title`      VARCHAR(50) NOT NULL COMMENT '渠道名称',
    `type`       VARCHAR(20) NOT NULL DEFAULT 'smtp' COMMENT 'smtp（预留 API 类扩展）',
    `config`     TEXT COMMENT '渠道配置 JSON：host/port/encryption/username/password/from_address/from_name',
    `is_default` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '默认发送渠道',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE = InnoDB COMMENT '邮件渠道';

CREATE TABLE IF NOT EXISTS `sys_mail_template` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`       VARCHAR(50) NOT NULL COMMENT '模板标识（业务引用键，如 verify_code）',
    `title`      VARCHAR(100) NOT NULL COMMENT '模板名称',
    `subject`    VARCHAR(200) NOT NULL COMMENT '邮件主题（支持 {$var} 变量）',
    `body`       MEDIUMTEXT COMMENT '邮件正文 HTML（支持 {$var} 变量）',
    `variables`  TEXT COMMENT '业务变量声明 JSON：[{name, label}]',
    `remark`     VARCHAR(200) NOT NULL DEFAULT '' COMMENT '用途说明',
    `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`)
) ENGINE = InnoDB COMMENT '邮件模板';

CREATE TABLE IF NOT EXISTS `sys_mail_log` (
    `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `channel_id`    INT UNSIGNED NULL COMMENT '发送渠道（未保存配置的测试为空）',
    `template_code` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '所用模板标识（自由内容为空）',
    `to_address`    VARCHAR(500) NOT NULL DEFAULT '' COMMENT '收件人（逗号分隔）',
    `subject`       VARCHAR(200) NOT NULL DEFAULT '' COMMENT '邮件主题',
    `body`          MEDIUMTEXT COMMENT '渲染后内容快照',
    `status`        TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1成功 0失败',
    `error`         VARCHAR(500) NOT NULL DEFAULT '' COMMENT '失败原因',
    `cost_ms`       INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '耗时毫秒',
    `created_at`    DATETIME DEFAULT CURRENT_TIMESTAMP,
    `updated_at`    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_created` (`created_at`)
) ENGINE = InnoDB COMMENT '邮件发送记录';
SQL);

        // 播种验证码模板（视觉参考 zhichangya 的 captcha 模板，品牌走 {$site_name} 文字而非内嵌 logo）
        $body = <<<'HTML'
<div style="background:#f6f8fa;padding:40px 16px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'PingFang SC','Hiragino Sans GB','Microsoft YaHei',sans-serif;">
  <div style="max-width:520px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 8px 30px rgba(15,23,42,.08);">
    <div style="text-align:center;padding:36px 24px 4px;font-size:20px;font-weight:700;color:#1f2937;">{$site_name}</div>
    <div style="padding:28px 40px 40px;">
      <h1 style="margin:0 0 10px;font-size:22px;font-weight:600;color:#1f2937;text-align:center;">{$action}</h1>
      <p style="margin:0 0 28px;font-size:14px;line-height:1.7;color:#6b7280;text-align:center;">您正在使用{$site_name}的{$action}功能，本次操作的验证码如下：</p>
      <div style="padding:22px 16px;background:#f9fafb;border:1px dashed #d1d5db;border-radius:10px;text-align:center;letter-spacing:10px;font-size:38px;font-weight:700;color:#2563eb;font-family:Consolas,Menlo,monospace;">{$code}</div>
      <p style="margin:24px 0 0;font-size:13px;line-height:1.8;color:#9ca3af;text-align:center;">
        验证码 <b style="color:#6b7280;">{$ttl}</b> 分钟内有效，请勿泄露给他人。<br>如非本人操作，请忽略此邮件。
      </p>
    </div>
    <div style="padding:18px 40px;background:#f9fafb;border-top:1px solid #eef0f3;text-align:center;">
      <p style="margin:0;font-size:12px;color:#9ca3af;">此邮件由{$site_name}系统自动发送，请勿直接回复 · © {$year} {$site_name}</p>
    </div>
  </div>
</div>
HTML;
        $variables = json_encode([
            ['name' => 'action', 'label' => '操作名称'],
            ['name' => 'code',   'label' => '验证码'],
            ['name' => 'ttl',    'label' => '有效分钟数'],
        ], JSON_UNESCAPED_UNICODE);
        $subject = '【{$site_name}】{$action}验证码';
        // SQL 字符串字面量转义单引号（正文样式含大量 '）
        $body = str_replace("'", "''", $body);
        $this->execute(
            "INSERT INTO `sys_mail_template` (`code`, `title`, `subject`, `body`, `variables`, `remark`)"
            . " VALUES ('verify_code', '邮箱验证码', '{$subject}', '{$body}', '{$variables}', '绑定邮箱/找回密码等验证码场景；site_name/year 为全局自动变量')"
        );
    }

    public function down(): void
    {
        $this->execute('DROP TABLE IF EXISTS `sys_mail_log`');
        $this->execute('DROP TABLE IF EXISTS `sys_mail_template`');
        $this->execute('DROP TABLE IF EXISTS `sys_mail_channel`');
    }
}
