<?php
declare(strict_types=1);

namespace App;

/** Mã hóa AES-256-GCM cho mật khẩu WordPress, API key... */
class Crypto
{
    private static function key(): string
    {
        $raw = (string)config('app_key', '');
        $key = base64_decode($raw, true);
        if ($key !== false && strlen($key) === 32) {
            return $key; // khóa do trình cài đặt tạo
        }
        if (strlen($raw) >= 16) {
            return hash('sha256', $raw, true); // chuỗi tự điền trong config.php
        }
        throw new \RuntimeException('app_key trong config.php phải là chuỗi ngẫu nhiên dài ít nhất 32 ký tự.');
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
