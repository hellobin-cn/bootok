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

return [
    'jwt_key'               => config('jwt.key'),
    'jwt_iss'               => config('jwt.iss'),
    'jwt_aud'               => config('jwt.aud'),
    'jwt_leeway'            => config('jwt.leeway'),
    'access_token_exp'      => config('jwt.access_token_exp'),
    'refresh_token_exp'     => config('jwt.refresh_token_exp'),
    'token_renew_threshold' => config('jwt.token_renew_threshold'),
];