<?php
declare(strict_types=1);

namespace App\Services;

/**
 * Kết nối WordPress qua REST API + Application Password
 * (WP Admin → Người dùng → Hồ sơ → Application Passwords).
 */
class WordPressService
{
    private string $base;

    public function __construct(string $siteUrl, private string $username, private string $appPassword)
    {
        $this->base = rtrim($siteUrl, '/');
    }

    private function endpoint(string $route, bool $pretty): string
    {
        [$path, $query] = array_pad(explode('?', $route, 2), 2, '');
        if ($pretty) {
            return $this->base . '/wp-json/wp/v2/' . ltrim($path, '/') . ($query !== '' ? '?' . $query : '');
        }
        return $this->base . '/?rest_route=/wp/v2/' . ltrim($path, '/') . ($query !== '' ? '&' . $query : '');
    }

    /** Gửi request; nếu /wp-json/ bị lỗi 404 thì thử lại với ?rest_route= */
    public function request(string $method, string $route, mixed $body = null, array $headers = []): array
    {
        $headers['Authorization'] = 'Basic ' . base64_encode($this->username . ':' . str_replace(' ', '', $this->appPassword));
        if (is_array($body)) {
            $body = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers['Content-Type'] = 'application/json; charset=utf-8';
        }
        $res = null;
        foreach ([true, false] as $pretty) {
            $res = http_request($method, $this->endpoint($route, $pretty), ['headers' => $headers, 'body' => $body, 'timeout' => 120]);
            if ($res['error'] === '' && $res['status'] !== 404 && is_array($res['json'])) {
                break;
            }
            if ($res['error'] === '' && $res['status'] === 404 && is_array($res['json']) && ($res['json']['code'] ?? '') !== 'rest_no_route') {
                break;
            }
        }
        if ($res['error'] !== '') {
            throw new \RuntimeException('Không kết nối được WordPress: ' . $res['error']);
        }
        if ($res['status'] >= 400 || !is_array($res['json'])) {
            $msg = is_array($res['json']) ? ($res['json']['message'] ?? 'HTTP ' . $res['status']) : 'Phản hồi không phải JSON (HTTP ' . $res['status'] . '). Có thể plugin bảo mật/Cloudflare đang chặn REST API.';
            if ($res['status'] === 401 || $res['status'] === 403) {
                $msg = 'Sai tài khoản/Application Password hoặc user không đủ quyền. (' . strip_tags((string)$msg) . ')';
            }
            throw new \RuntimeException('WordPress: ' . strip_tags((string)$msg));
        }
        return $res['json'];
    }

    public function me(): array
    {
        return $this->request('GET', 'users/me?context=edit');
    }

    public function categories(): array
    {
        $all = [];
        for ($page = 1; $page <= 10; $page++) {
            $batch = $this->request('GET', 'categories?per_page=100&page=' . $page . '&_fields=id,name,parent');
            $all = array_merge($all, $batch);
            if (count($batch) < 100) {
                break;
            }
        }
        return $all;
    }

    /** Lấy toàn bộ bài viết + trang đã đăng (link, tiêu đề) để làm kho interlink. */
    public function publishedContent(int $maxPages = 30): array
    {
        $items = [];
        foreach (['posts', 'pages'] as $type) {
            for ($page = 1; $page <= $maxPages; $page++) {
                try {
                    $batch = $this->request('GET', $type . '?status=publish&per_page=100&page=' . $page . '&_fields=link,title');
                } catch (\RuntimeException $e) {
                    if ($page > 1) {
                        break; // hết trang
                    }
                    throw $e;
                }
                foreach ($batch as $row) {
                    $items[] = ['url' => $row['link'], 'title' => html_entity_decode(strip_tags($row['title']['rendered'] ?? ''), ENT_QUOTES, 'UTF-8')];
                }
                if (count($batch) < 100) {
                    break;
                }
            }
        }
        return $items;
    }

    /** Upload ảnh vào thư viện Media. Trả về [id, source_url]. */
    public function uploadMedia(string $filePath, string $fileName, string $alt, string $title): array
    {
        $mime = match (strtolower(pathinfo($filePath, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            default => 'image/webp',
        };
        $media = $this->request('POST', 'media', (string)file_get_contents($filePath), [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
        ]);
        $this->request('POST', 'media/' . $media['id'], ['alt_text' => $alt, 'title' => $title, 'caption' => '']);
        return [(int)$media['id'], (string)$media['source_url']];
    }

    public function savePost(?int $postId, array $data): array
    {
        return $this->request('POST', $postId ? 'posts/' . $postId : 'posts', $data);
    }

    /** Tìm hoặc tạo tag theo tên, trả về danh sách ID. */
    public function tagIds(array $names): array
    {
        $ids = [];
        foreach (array_slice(array_filter(array_map('trim', $names)), 0, 10) as $name) {
            $found = $this->request('GET', 'tags?search=' . rawurlencode($name) . '&_fields=id,name');
            $match = null;
            foreach ($found as $t) {
                if (mb_strtolower(html_entity_decode($t['name'])) === mb_strtolower($name)) {
                    $match = (int)$t['id'];
                }
            }
            if ($match === null) {
                try {
                    $match = (int)$this->request('POST', 'tags', ['name' => $name])['id'];
                } catch (\RuntimeException) {
                    continue;
                }
            }
            $ids[] = $match;
        }
        return $ids;
    }
}
