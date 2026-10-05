<?php
declare(strict_types=1);

namespace App;

/** Mã hóa AES-256-GCM cho mật khẩu WordPress, API key... */
class Crypto
{
    private static function key(): string
    {
        $raw = (string)config('app_key', '');
        if ($raw === '') {
            $raw = self::keyFile();
        }
        $key = base64_decode($raw, true);
        if ($key !== false && strlen($key) === 32) {
            return $key; // khóa do trình cài đặt tạo
        }
        if (strlen($raw) >= 16) {
            return hash('sha256', $raw, true); // chuỗi tự điền trong config.php
        }
        throw new \RuntimeException('app_key trong config.php phải là chuỗi ngẫu nhiên dài ít nhất 32 ký tự.');
    }

    /**
     * Khi config.php không khai báo APP_KEY: tự tạo khóa lưu ở storage/app.key (lần đầu chạy).
     * File này nằm ngoài Git; nhớ sao lưu cùng database.
     */
    private static function keyFile(): string
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        $file = BASE_PATH . '/storage/app.key';
        if (!is_file($file)) {
            if (!is_dir(dirname($file))) {
                @mkdir(dirname($file), 0775, true);
            }
            $fp = @fopen($file, 'x');               // 'x': không ghi đè nếu tiến trình khác vừa tạo
            if ($fp) {
                fwrite($fp, base64_encode(random_bytes(32)));
                fclose($fp);
                @chmod($file, 0600);
            }
        }
        $cached = trim((string)@file_get_contents($file));
        if ($cached === '') {
            throw new \RuntimeException('Không tạo được khóa mã hóa storage/app.key (kiểm tra quyền ghi thư mục).');
        }
        return $cached;
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
