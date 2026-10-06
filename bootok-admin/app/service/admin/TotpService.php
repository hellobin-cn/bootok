<?php

namespace app\service\admin;

/**
 * TOTP 两步验证（RFC 6238）：纯 PHP 实现，无 SDK 依赖
 * SHA1 + 6 位 + 30 秒窗口（Google Authenticator/钉钉等主流验证器默认参数）
 */
class TotpService
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** 生成 base32 密钥（默认 160 位熵，32 字符） */
    public static function generateSecret(int $bytes = 20): string
    {
        $raw = random_bytes($bytes);
        $out = '';
        $bits = 0;
        $value = 0;
        for ($i = 0, $n = strlen($raw); $i < $n; $i++) {
            $value = ($value << 8) | ord($raw[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= self::ALPHABET[($value >> $bits) & 31];
            }
        }
        if ($bits > 0) {
            $out .= self::ALPHABET[($value << (5 - $bits)) & 31];
        }
        return $out;
    }

    /** 验证器扫描串 */
    public static function otpauthUri(string $secret, string $account, string $issuer = 'bootok'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=6&period=30';
    }

    /** 某时刻的动态码 */
    public static function codeAt(string $secret, ?int $timestamp = null, int $period = 30, int $digits = 6): string
    {
        $key = self::base32Decode($secret);
        if ($key === '') {
            return '';
        }
        $counter = intdiv($timestamp ?? time(), $period);
        $hash = hash_hmac('sha1', pack('N*', 0, $counter), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24)
            | ((ord($hash[$offset + 1]) & 0xFF) << 16)
            | ((ord($hash[$offset + 2]) & 0xFF) << 8)
            | (ord($hash[$offset + 3]) & 0xFF);

        return str_pad((string) ($value % 10 ** $digits), $digits, '0', STR_PAD_LEFT);
    }

    /** 校验动态码，允许前后各 window 个窗口的时钟漂移 */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{6}$/', $code) || $secret === '') {
            return false;
        }
        $now = time();
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::codeAt($secret, $now + $i * 30), $code)) {
                return true;
            }
        }
        return false;
    }

    public static function base32Decode(string $encoded): string
    {
        $encoded = strtoupper(rtrim($encoded, '='));
        $out = '';
        $bits = 0;
        $value = 0;
        for ($i = 0, $n = strlen($encoded); $i < $n; $i++) {
            $pos = strpos(self::ALPHABET, $encoded[$i]);
            if ($pos === false) {
                return '';
            }
            $value = ($value << 5) | $pos;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($value >> $bits) & 255);
            }
        }
        return $out;
    }
}
