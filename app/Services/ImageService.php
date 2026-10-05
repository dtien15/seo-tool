<?php
declare(strict_types=1);

namespace App\Services;

use App\Settings;

/** Tạo ảnh bằng OpenAI Images API, lưu file WebP vào thư mục uploads/. */
class ImageService
{
    public function __construct(
        private string $apiKey,
        private string $model,
        private string $size,
        private string $quality,
    ) {
    }

    public static function fromSettings(): self
    {
        $key = Settings::get('openai_api_key', '');
        if ($key === '' || $key === null) {
            throw new \RuntimeException('Chưa nhập OpenAI API key (tạo ảnh) trong Cài đặt hệ thống.');
        }
        return new self($key, (string)Settings::get('image_model'), (string)Settings::get('image_size'), (string)Settings::get('image_quality'));
    }

    public function model(): string
    {
        return $this->model;
    }

    /** Tạo ảnh và trả về đường dẫn tương đối (ví dụ uploads/3/12-0-ab12.webp). */
    public function generate(string $prompt, int $projectId, string $baseName): string
    {
        $body = [
            'model' => $this->model,
            'prompt' => $prompt,
            'n' => 1,
            'size' => $this->size,
            'quality' => $this->quality,
            'output_format' => 'webp',
            'output_compression' => 82,
        ];
        $base = rtrim((string)(getenv('OPENAI_BASE_URL') ?: 'https://api.openai.com/v1'), '/');
        $res = http_request('POST', $base . '/images/generations', [
            'headers' => ['Authorization' => 'Bearer ' . $this->apiKey, 'Content-Type' => 'application/json'],
            'body' => json_encode($body, JSON_UNESCAPED_UNICODE),
            'timeout' => 240,
        ]);
        if ($res['error']) {
            throw new \RuntimeException('Không gọi được OpenAI: ' . $res['error']);
        }
        if ($res['status'] !== 200) {
            $msg = $res['json']['error']['message'] ?? ('HTTP ' . $res['status']);
            throw new \RuntimeException('OpenAI tạo ảnh lỗi: ' . $msg);
        }
        $b64 = $res['json']['data'][0]['b64_json'] ?? null;
        if (!$b64) {
            throw new \RuntimeException('OpenAI không trả về dữ liệu ảnh.');
        }
        $binary = base64_decode($b64);
        $ext = $this->detectExtension($binary);

        $dir = 'uploads/' . $projectId . '/' . date('Y-m');
        if (!is_dir(BASE_PATH . '/' . $dir) && !mkdir(BASE_PATH . '/' . $dir, 0775, true) && !is_dir(BASE_PATH . '/' . $dir)) {
            throw new \RuntimeException('Không tạo được thư mục ' . $dir . ' (kiểm tra quyền ghi).');
        }
        $relative = $dir . '/' . $baseName . '-' . substr(random_token(4), 0, 6) . '.' . $ext;
        file_put_contents(BASE_PATH . '/' . $relative, $binary);
        return $relative;
    }

    private function detectExtension(string $binary): string
    {
        return match (true) {
            str_starts_with($binary, "\x89PNG") => 'png',
            str_starts_with($binary, "\xFF\xD8") => 'jpg',
            default => 'webp',
        };
    }
}
