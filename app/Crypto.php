<?php
declare(strict_types=1);

namespace App;

/** Mã hóa AES-256-GCM cho mật khẩu WordPress, API key... */
class Crypto
{
    private static function key(): string
    {
        $key = base64_decode((string)config('app_key', ''), true);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('APP_KEY trong config.php không hợp lệ.');
        }
        return $key;
    }

    public static function encrypt(?string $plain): ?string
    {
        if ($plain === null || $plain === '') {
            return null;
        }
        $iv = random_bytes(12);
        $tag = '';
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $cipher);
    }

    public static function decrypt(?string $encoded): ?string
    {
        if ($encoded === null || $encoded === '') {
            return null;
        }
        $raw = base64_decode($encoded, true);
        if ($raw === false || strlen($raw) < 29) {
            return null;
        }
        $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', self::key(), OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
        return $plain === false ? null : $plain;
    }
}
