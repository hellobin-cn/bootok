<?php

namespace app\model;

/**
 * 个人界面偏好（sys_user_pref）：页面配置/主题/语言，一管理员一行
 * 个人数据，不经 /auto 暴露；UserPrefController 白名单读写
 *
 * @property int   $admin_id
 * @property array $prefs
 */
class UserPref extends Model
{
    protected $table = 'sys_user_pref';
    protected $primaryKey = 'admin_id';

    protected $casts = [
        'prefs' => 'array',
    ];
}
