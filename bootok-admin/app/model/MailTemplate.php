<?php

namespace app\model;

use app\attribute\AutoResource;

/**
 * 邮件模板（DB 存储，界面编辑/预览；业务按 code 引用）
 *
 * @property int    $id
 * @property string $code      模板标识（业务引用键，如 verify_code）
 * @property string $title     模板名称
 * @property string $subject   邮件主题（支持 {$var} 变量）
 * @property string $body      邮件正文 HTML（支持 {$var} 变量）
 * @property array  $variables 业务变量声明 [{name, label}]
 * @property string $remark    用途说明
 */
#[AutoResource(name: '邮件模板', only: ['getRows', 'getItem', 'create', 'edit', 'delete'])]
class MailTemplate extends Model
{
    protected $table = 'sys_mail_template';

    protected $casts = [
        'variables' => 'array',
    ];

    public function beforeSave(): string
    {
        $code = trim((string) ($this->code ?? ''));
        if ($code === '') {
            return '模板标识不能为空';
        }
        if (!preg_match('/^[a-z][a-z0-9_]{1,49}$/', $code)) {
            return '模板标识需为小写字母开头的 a-z0-9_（2-50 位）';
        }
        $exists = self::where('code', $code);
        if ($this->id) {
            $exists->where('id', '!=', $this->id);
        }
        if ($exists->exists()) {
            return "模板标识 {$code} 已存在";
        }
        if (trim((string) ($this->subject ?? '')) === '') {
            return '邮件主题不能为空';
        }

        // 变量声明归一化：name 必填，label 缺省同 name
        $variables = is_array($this->variables) ? $this->variables : [];
        $normalized = [];
        foreach ($variables as $item) {
            $name = trim((string) ($item['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $normalized[] = ['name' => $name, 'label' => trim((string) ($item['label'] ?? '')) ?: $name];
        }
        $this->variables = $normalized;

        return '';
    }
}
