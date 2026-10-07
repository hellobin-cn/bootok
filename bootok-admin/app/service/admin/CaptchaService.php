<?php

namespace app\service\admin;

use support\Redis;

/**
 * 图形验证码：纯 SVG 手绘（无 GD/imagick 扩展依赖），字符形变 + 干扰线
 * 答案存 Redis（5 分钟一次性，忽略大小写）
 */
class CaptchaService
{
    private const TTL = 300;
    private const PREFIX = 'captcha:';

    /** 去掉易混淆字符的码表 */
    private const CHARS = '3456789ABCDEFGHJKMNPQRTUVWXYZ';

    /**
     * @return array{key: string, svg: string}
     */
    public function issue(): array
    {
        $code = '';
        for ($i = 0; $i < 4; $i++) {
            $code .= self::CHARS[random_int(0, strlen(self::CHARS) - 1)];
        }
        $key = bin2hex(random_bytes(12));
        Redis::setex(self::PREFIX . $key, self::TTL, strtolower($code));
        return ['key' => $key, 'svg' => $this->render($code)];
    }

    public function verify(string $key, string $code): bool
    {
        $key = trim($key);
        $code = trim($code);
        if ($key === '' || $code === '' || !preg_match('/^[a-f0-9]{24}$/', $key)) {
            return false;
        }
        $redisKey = self::PREFIX . $key;
        $saved = Redis::get($redisKey);
        if ($saved === false || $saved === null) {
            return false;
        }
        Redis::del($redisKey); // 一次性
        return hash_equals((string) $saved, strtolower($code));
    }

    /**
     * SVG：每字符随机旋转/位移/颜色，叠加干扰线与噪点
     */
    private function render(string $code): string
    {
        $width = 130;
        $height = 44;
        $colors = ['#165DFF', '#00B42A', '#F7BA1E', '#722ED1', '#F53F3F', '#14C9C9'];
        $svg = "<svg xmlns=\"http://www.w3.org/2000/svg\" width=\"{$width}\" height=\"{$height}\" viewBox=\"0 0 {$width} {$height}\">";
        $svg .= "<rect width=\"{$width}\" height=\"{$height}\" fill=\"#f2f3f5\" rx=\"4\"/>";
        // 干扰线
        for ($i = 0; $i < 4; $i++) {
            $c = $colors[random_int(0, 5)];
            $svg .= sprintf(
                '<path d="M%d,%d Q%d,%d %d,%d" stroke="%s" stroke-width="1" fill="none" opacity="0.5"/>',
                random_int(0, 30), random_int(5, 39), random_int(45, 85), random_int(0, 44), random_int(100, 130), random_int(5, 39), $c
            );
        }
        // 噪点
        for ($i = 0; $i < 30; $i++) {
            $svg .= sprintf(
                '<circle cx="%d" cy="%d" r="1" fill="%s" opacity="0.4"/>',
                random_int(0, $width), random_int(0, $height), $colors[random_int(0, 5)]
            );
        }
        // 字符
        $len = strlen($code);
        for ($i = 0; $i < $len; $i++) {
            $x = 14 + $i * 28 + random_int(-2, 2);
            $y = 30 + random_int(-3, 3);
            $angle = random_int(-22, 22);
            $color = $colors[random_int(0, 5)];
            $svg .= sprintf(
                '<text x="%d" y="%d" font-size="26" font-weight="bold" font-family="Consolas, monospace" fill="%s" transform="rotate(%d %d %d)" text-anchor="middle">%s</text>',
                $x, $y, $color, $angle, $x, $y, htmlspecialchars($code[$i])
            );
        }
        $svg .= '</svg>';
        return $svg;
    }
}
