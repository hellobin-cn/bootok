<?php

namespace app\validate;

class Article extends Validate
{
    protected array $rule = [
        'title' => 'require|max:100',
    ];

    protected array $message = [
        'title.require' => '请输入标题',
        'title.max'     => '标题不超过100个字符',
    ];

    protected array $scene = [
        'create' => ['title'],
        'edit'   => ['title'],
    ];
}
