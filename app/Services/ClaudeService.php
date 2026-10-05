<?php
declare(strict_types=1);

namespace App\Services;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIStatusException;
use App\Settings;

/** Gọi Claude API qua SDK chính thức (anthropic-ai/sdk). */
class ClaudeService
{
    /** Các model hỗ trợ server-side fallback khi bị bộ lọc an toàn từ chối. */
    private const FALLBACK_MODELS = ['claude-opus-5-5', 'claude-sonnet-5-5'];

    public function __construct(
        private string $apiKey,
        private string $model,
        private string $effort = 'medium',
    ) {
    }

    public static function fromSettings(): self
    {
        $key = Settings::get('anthropic_api_key', '');
        if ($key === '' || $key === null) {
            throw new \RuntimeException('Chưa nhập Claude API key trong Cài đặt hệ thống.');
        }
        return new self($key, (string)Settings::get('claude_model'), (string)Settings::get('claude_effort'));
    }

    public function model(): string
    {
        return $this->model;
    }

    /**
     * @return array{text: string, input_tokens: int, output_tokens: int, model: string}
     */
    public function complete(string $system, string $prompt, int $maxTokens = 16000): array
    {
        if (!class_exists(Client::class)) {
            throw new \RuntimeException('Chưa cài thư viện Anthropic SDK. Chạy "composer install" trong thư mục web trên hosting.');
        }
        $client = new Client(apiKey: $this->apiKey);

        $params = [
            'model' => $this->model,
            'maxTokens' => $maxTokens,
            'system' => $system,
            'messages' => [['role' => 'user', 'content' => $prompt]],
        ];
        // Haiku 4.5 không hỗ trợ tham số effort.
        if (!str_starts_with($this->model, 'claude-haiku')) {
            $params['outputConfig'] = ['effort' => $this->effort];
        }
        if (in_array($this->model, self::FALLBACK_MODELS, true)) {
            $params['betas'] = ['server-side-fallback-2026-07-01'];
            $params['fallbacks'] = 'default';
        }

        try {
            $message = $client->beta->messages->create(...$params);
        } catch (APIStatusException $e) {
            $type = $e->type?->value ?? 'api_error';
            $hint = match ($type) {
                'authentication_error' => 'API key Claude không đúng.',
                'rate_limit_error' => 'Vượt giới hạn tốc độ, hệ thống sẽ thử lại sau.',
                'overloaded_error' => 'Claude đang quá tải, hệ thống sẽ thử lại sau.',
                'permission_error' => 'API key không có quyền dùng model này.',
                default => $e->getMessage(),
            };
            throw new \RuntimeException('Claude API: ' . $hint, 0, $e);
        }

        $stop = $message->stopReason;
        $stop = $stop instanceof \BackedEnum ? $stop->value : (string)$stop;
        if ($stop === 'refusal') {
            throw new \RuntimeException('Claude từ chối yêu cầu này (bộ lọc an toàn). Hãy chỉnh lại từ khóa/ghi chú.');
        }

        $text = '';
        foreach ($message->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }
        if ($stop === 'max_tokens') {
            throw new \RuntimeException('Bài viết vượt quá giới hạn độ dài một lần tạo. Hãy giảm số từ mục tiêu.');
        }

        return [
            'text' => $text,
            'input_tokens' => (int)($message->usage->inputTokens ?? 0),
            'output_tokens' => (int)($message->usage->outputTokens ?? 0),
            'model' => $this->model,
        ];
    }
}
