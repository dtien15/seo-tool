<?php
declare(strict_types=1);

namespace App\Services;

use App\Settings;
use App\Usage;

/**
 * Các AI đã kết nối (Cài đặt hệ thống → Kết nối AI). Mỗi chức năng tự chọn AI khi dùng,
 * chỉ hiện các AI đã có key và không bị lỗi ở lần kiểm tra gần nhất.
 */
class AiText
{
    /** provider => [tên hiển thị, khả năng] */
    public const PROVIDERS = [
        'anthropic' => ['Claude (Anthropic)', ['text']],
        'openai' => ['OpenAI', ['text', 'image']],
    ];

    private const KEY = ['anthropic' => 'anthropic_api_key', 'openai' => 'openai_api_key'];

    public static function label(string $provider): string
    {
        return self::PROVIDERS[$provider][0] ?? $provider;
    }

    /** Trạng thái lần kiểm tra gần nhất: ['ok' => bool, 'message' => string, 'at' => 'Y-m-d H:i:s'] hoặc null */
    public static function status(string $provider): ?array
    {
        $s = json_decode((string)Settings::get('ai_status_' . $provider, ''), true);
        return is_array($s) ? $s : null;
    }

    public static function hasKey(string $provider): bool
    {
        return isset(self::KEY[$provider]) && Settings::has(self::KEY[$provider]);
    }

    /** Đủ cấu hình để làm việc loại $cap (text = viết, image = tạo hình). */
    private static function configured(string $provider, string $cap): bool
    {
        if (!self::hasKey($provider) || !in_array($cap, self::PROVIDERS[$provider][1], true)) {
            return false;
        }
        if ($provider === 'openai' && $cap === 'text') {
            return trim((string)Settings::get('openai_text_model', '')) !== '';
        }
        return true;
    }

    /** Các AI dùng được cho loại việc $cap: có key, đủ cấu hình, lần kiểm tra gần nhất không lỗi. */
    public static function connected(string $cap = 'text'): array
    {
        $out = [];
        foreach (array_keys(self::PROVIDERS) as $p) {
            $st = self::status($p);
            if (self::configured($p, $cap) && ($st === null || !empty($st['ok']))) {
                $out[$p] = self::label($p) . ($st === null ? ' (chưa kiểm tra)' : '');
            }
        }
        return $out;
    }

    public static function ready(string $cap = 'text'): bool
    {
        return (bool)self::connected($cap);
    }

    /** Chọn AI theo yêu cầu người dùng; không hợp lệ thì lấy AI đầu tiên đã kết nối. */
    public static function pick(?string $requested, string $cap = 'text'): string
    {
        $list = self::connected($cap);
        if (!$list) {
            throw new \RuntimeException($cap === 'image'
                ? 'Chưa kết nối AI tạo hình (OpenAI) trong Cài đặt hệ thống.'
                : 'Chưa kết nối AI nào trong Cài đặt hệ thống.');
        }
        return ($requested !== null && isset($list[$requested])) ? $requested : (string)array_key_first($list);
    }

    /**
     * Gọi AI viết (AI do $ctx['ai'] chỉ định) và ghi nhận chi phí.
     * @return array{text: string, input_tokens: int, output_tokens: int, model: string}
     */
    public static function complete(string $system, string $prompt, int $maxTokens, array $ctx, string $task): array
    {
        $provider = self::pick($ctx['ai'] ?? null);
        $res = self::call($provider, $system, $prompt, $maxTokens);
        $cost = $provider === 'openai'
            ? $res['input_tokens'] / 1_000_000 * (float)Settings::get('openai_price_in', '0') + $res['output_tokens'] / 1_000_000 * (float)Settings::get('openai_price_out', '0')
            : Usage::claudeCost($res['model'], $res['input_tokens'], $res['output_tokens']);
        Usage::log($ctx, $provider, $res['model'], $task, $res['input_tokens'], $res['output_tokens'], 0, $cost);
        return $res;
    }

    private static function call(string $provider, string $system, string $prompt, int $maxTokens): array
    {
        return $provider === 'openai'
            ? OpenAiText::fromSettings()->complete($system, $prompt, $maxTokens)
            : ClaudeService::fromSettings()->complete($system, $prompt, $maxTokens);
    }

    /** Kiểm tra kết nối một AI và lưu kết quả. */
    public static function test(string $provider): array
    {
        try {
            if (!isset(self::PROVIDERS[$provider])) {
                throw new \RuntimeException('AI không hợp lệ.');
            }
            if (!self::hasKey($provider)) {
                throw new \RuntimeException('Chưa nhập API key.');
            }
            if ($provider === 'openai') {
                $base = rtrim((string)(getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1'), '/');
                $res = http_request('GET', $base . '/models', ['headers' => ['Authorization' => 'Bearer ' . Settings::get('openai_api_key')], 'timeout' => 20]);
                if ($res['status'] !== 200) {
                    throw new \RuntimeException('OpenAI: ' . ($res['json']['error']['message'] ?? ($res['error'] ?: 'HTTP ' . $res['status'])));
                }
                $msg = 'API key hợp lệ';
                if (trim((string)Settings::get('openai_text_model', '')) !== '') {
                    $r = self::call('openai', 'Bạn là trợ lý ngắn gọn.', 'Trả lời đúng: OK', 200);
                    $msg .= ' · viết bài bằng ' . $r['model'] . ': OK';
                } else {
                    $msg .= ' · chưa nhập model viết bài (chỉ dùng tạo hình)';
                }
            } else {
                $r = self::call('anthropic', 'Bạn là trợ lý ngắn gọn.', 'Trả lời đúng: OK', 2000);
                $msg = 'Kết nối thành công · ' . $r['model'];
            }
            $status = ['ok' => true, 'message' => $msg, 'at' => now()];
        } catch (\Throwable $e) {
            $status = ['ok' => false, 'message' => $e->getMessage(), 'at' => now()];
        }
        Settings::set('ai_status_' . $provider, json_encode($status, JSON_UNESCAPED_UNICODE));
        return $status;
    }
}
