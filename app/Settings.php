<?php
declare(strict_types=1);

namespace App;

/** Cài đặt hệ thống (lưu trong bảng settings). Các key bí mật được mã hóa. */
class Settings
{
    public const SECRET_KEYS = ['anthropic_api_key', 'openai_api_key', 'google_service_account'];

    public const DEFAULTS = [
        'claude_model' => 'claude-opus-5-5',
        'claude_effort' => 'medium',
        'image_model' => 'gpt-image-1',
        'image_size' => '1536x1024',
        'image_quality' => 'medium',
        'image_price_usd' => '0.05',
        'default_monthly_budget' => '',
    ];

    /** Model Claude cho phép chọn: id => [tên, giá input $/1M token, giá output $/1M token] */
    public const CLAUDE_MODELS = [
        'claude-opus-5-5' => ['Claude Opus 5.5 (chất lượng cao nhất)', 4.0, 20.0],
        'claude-sonnet-5-5' => ['Claude Sonnet 5.5 (cân bằng)', 2.0, 10.0],
        'claude-haiku-4-5' => ['Claude Haiku 4.5 (rẻ, nhanh)', 1.0, 5.0],
    ];

    private static ?array $cache = null;

    private static function load(): array
    {
        if (self::$cache === null) {
            self::$cache = [];
            foreach (db()->fetchAll('SELECT k, v FROM settings') as $row) {
                self::$cache[$row['k']] = $row['v'];
            }
        }
        return self::$cache;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $all = self::load();
        $value = $all[$key] ?? null;
        if ($value !== null && in_array($key, self::SECRET_KEYS, true)) {
            $value = Crypto::decrypt($value);
        }
        if ($value === null || $value === '') {
            return $default ?? (self::DEFAULTS[$key] ?? null);
        }
        return $value;
    }

    public static function set(string $key, ?string $value): void
    {
        $stored = in_array($key, self::SECRET_KEYS, true) ? Crypto::encrypt($value) : $value;
        db()->query('REPLACE INTO settings (k, v) VALUES (?, ?)', [$key, $stored]);
        self::$cache = null;
    }

    public static function has(string $key): bool
    {
        return (string)self::get($key, '') !== '';
    }
}
