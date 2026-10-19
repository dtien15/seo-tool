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
        return new self($key, AiText::openaiTextModel());
    }

    /** @return array{text: string, input_tokens: int, output_tokens: int, model: string} */
    public function complete(string $system, string $prompt, int $maxTokens = 16000): array
    {
        $base = rtrim((string)(getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1'), '/');
        $body = [
            'model' => $this->model,
            'messages' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $prompt],
            ],
            'max_completion_tokens' => $maxTokens,
        ];
        // Model suy luận (gpt-5..., o1/o3/o4...): token suy nghĩ tính chung vào giới hạn -> nghĩ ít + chừa thêm chỗ
        if (preg_match('~^(gpt-5|o\d)~', $this->model)) {
            $body['reasoning_effort'] = 'low';
            $body['max_completion_tokens'] = $maxTokens + 16000;
        }
        $res = http_request('POST', $base . '/chat/completions', [
            'headers' => ['Authorization' => 'Bearer ' . $this->apiKey, 'Content-Type' => 'application/json'],
            'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
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
            throw new \RuntimeException('AI trả lời quá dài, vượt giới hạn một lần tạo (' . $this->model . '). Thử lại, giảm số từ mục tiêu hoặc dùng model khác.');
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
