<?php
declare(strict_types=1);

namespace App\Services;

use App\Settings;

/** Viết bài bằng OpenAI API (Chat Completions). */
class OpenAiText
{
    public function __construct(private string $apiKey, private string $model)
    {
    }

    public static function fromSettings(): self
    {
        $key = Settings::get('openai_api_key', '');
        if ($key === '' || $key === null) {
            throw new \RuntimeException('Chưa nhập OpenAI API key trong Cài đặt hệ thống.');
        }
        $model = trim((string)Settings::get('openai_text_model', ''));
        if ($model === '') {
            throw new \RuntimeException('Chưa nhập tên model OpenAI dùng để viết bài trong Cài đặt hệ thống.');
        }
        return new self($key, $model);
    }

    /** @return array{text: string, input_tokens: int, output_tokens: int, model: string} */
    public function complete(string $system, string $prompt, int $maxTokens = 16000): array
    {
        $base = rtrim((string)(getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1'), '/');
        $res = http_request('POST', $base . '/chat/completions', [
            'headers' => ['Authorization' => 'Bearer ' . $this->apiKey, 'Content-Type' => 'application/json'],
            'body' => json_encode([
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'max_completion_tokens' => $maxTokens,
            ], JSON_UNESCAPED_UNICODE),
            'timeout' => 600,
        ]);
        if ($res['error'] !== '') {
            throw new \RuntimeException('Không gọi được OpenAI: ' . $res['error']);
        }
        if ($res['status'] !== 200) {
            $msg = $res['json']['error']['message'] ?? ('HTTP ' . $res['status']);
            throw new \RuntimeException('OpenAI: ' . $msg);
        }
        $choice = $res['json']['choices'][0] ?? [];
        if (($choice['finish_reason'] ?? '') === 'length') {
            throw new \RuntimeException('Bài viết vượt quá giới hạn độ dài một lần tạo. Hãy giảm số từ mục tiêu.');
        }
        $text = (string)($choice['message']['content'] ?? '');
        if ($text === '') {
            throw new \RuntimeException('OpenAI không trả về nội dung' . (!empty($choice['message']['refusal']) ? ': ' . $choice['message']['refusal'] : '.'));
        }
        return [
            'text' => $text,
            'input_tokens' => (int)($res['json']['usage']['prompt_tokens'] ?? 0),
            'output_tokens' => (int)($res['json']['usage']['completion_tokens'] ?? 0),
            'model' => $this->model,
        ];
    }
}
