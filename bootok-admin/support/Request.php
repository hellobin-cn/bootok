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

namespace support;

/**
 * Class Request
 * @package support
 */
class Request extends \Webman\Http\Request
{
    public function setCustomData($key, $value): void
    {
        $this->data['custom'][$key] = $value;
    }

    public function getCustomData($key = null, $default = null)
    {
        if ($key) {
            return $this->data['custom'][$key] ?? $default;
        }
        return $this->data['custom'] ?? null;
    }
}