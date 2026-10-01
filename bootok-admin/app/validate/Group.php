<?php

namespace app\validate;

class Group extends Validate
{
    protected array $rule = [
        'group_name' => 'require|max:50',
    ];

    protected array $message = [
        'group_name.require' => '请输入名称',
        'group_name.max'     => '名称不超过50个字符',
    ];

    protected array $scene = [
        'create' => ['group_name'],
        'edit'   => ['group_name'],
    ];
}
