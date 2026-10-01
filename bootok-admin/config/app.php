<?php
/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

use support\Request;

return [
    'name'              => get_env('APP_NAME', 'app'),
    // 生产环境必须在 .env 中显式设置 APP_DEBUG=false
    'debug'             => in_array(strtolower((string)get_env('APP_DEBUG', 'true')), ['1', 'true', 'on', 'yes']),
    'error_reporting'   => E_ALL,
    'default_timezone'  => 'Asia/Shanghai',
    'request_class'     => Request::class,
    'public_path'       => base_path() . DIRECTORY_SEPARATOR . 'public',
    'runtime_path'      => base_path(false) . DIRECTORY_SEPARATOR . 'runtime',
    'controller_suffix' => 'Controller',
    'controller_reuse'  => false,
];
