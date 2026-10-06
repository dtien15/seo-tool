<?php
declare(strict_types=1);

namespace App\Services;

use App\Settings;
use App\Usage;

/**
 * Chọn nhà cung cấp AI viết bài theo Cài đặt hệ thống:
 *   anthropic – Claude API, openai – OpenAI API.
 */
class AiText
{
    public const PROVIDERS = [
        'anthropic' => 'Claude API (Anthropic)',
        'openai' => 'OpenAI API',
    ];

    public static function provider(): string
    {
        $p = (string)Settings::get('ai_provider', 'anthropic');
        return isset(self::PROVIDERS[$p]) ? $p : 'anthropic';
    }

    /** Đã đủ cấu hình để chạy AI tự động chưa. */
    public static function ready(): bool
    {
        return match (self::provider()) {
            'anthropic' => Settings::has('anthropic_api_key'),
            'openai' => Settings::has('openai_api_key') && trim((string)Settings::get('openai_text_model', '')) !== '',
        };
    }

    /**
     * Gọi AI và ghi nhận chi phí.
     * @return array{text: string, input_tokens: int, output_tokens: int, model: string}
     */
    public static function complete(string $system, string $prompt, int $maxTokens, array $ctx, string $task): array
    {
        $provider = self::provider();
        $res = match ($provider) {
            'openai' => OpenAiText::fromSettings()->complete($system, $prompt, $maxTokens),
            default => ClaudeService::fromSettings()->complete($system, $prompt, $maxTokens),
        };
        $cost = $provider === 'openai'
            ? $res['input_tokens'] / 1_000_000 * (float)Settings::get('openai_price_in', '0') + $res['output_tokens'] / 1_000_000 * (float)Settings::get('openai_price_out', '0')
            : Usage::claudeCost($res['model'], $res['input_tokens'], $res['output_tokens']);
        Usage::log($ctx, $provider, $res['model'], $task, $res['input_tokens'], $res['output_tokens'], 0, $cost);
        return $res;
    }
}
