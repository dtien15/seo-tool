<?php
declare(strict_types=1);

namespace App;

use App\Models\Article;
use App\Models\Project;
use App\Services\ClaudeService;
use App\Services\ContentWriter;
use App\Services\ImageService;
use App\Services\InternalLinker;
use App\Services\SheetSync;
use App\Services\SitemapService;

/** Xử lý từng loại công việc trong hàng đợi. */
class Jobs
{
    public static function handle(array $job): void
    {
        $payload = json_decode((string)$job['payload'], true) ?: [];
        $ctx = ['user_id' => $job['user_id'], 'project_id' => $job['project_id'], 'article_id' => $job['article_id']];
        match ($job['type']) {
            'outline' => self::outline((int)$payload['article_id'], $ctx),
            'write' => self::write((int)$payload['article_id'], $ctx),
            'images' => self::images((int)$payload['article_id'], $ctx, false),
            'image' => self::regenerateImage((int)$payload['article_id'], (int)$payload['image_id'], $ctx, $payload['prompt'] ?? null),
            'publish' => self::publish((int)$payload['article_id']),
            'import_sitemap' => self::importSitemap((int)$job['project_id']),
            'import_wordpress' => self::importWordPress((int)$job['project_id']),
            'fetch_titles' => self::fetchTitles((int)$job['project_id']),
            'sheet_push' => self::sheetPush((int)$payload['article_id']),
            'sheet_push_all' => SheetSync::pushAll(self::project((int)$job['project_id'])),
            'sheet_pull' => SheetSync::pull(self::project((int)$job['project_id'])),
            default => throw new \RuntimeException('Loại công việc không hỗ trợ: ' . $job['type']),
        };
    }

    /** Các loại job gắn với trạng thái AI của bài viết. */
    public static function isArticleTask(string $type): bool
    {
        return in_array($type, ['outline', 'write', 'images', 'image', 'publish'], true);
    }

    private static function project(int $id): array
    {
        $p = Project::find($id);
        if (!$p) {
            throw new \RuntimeException('Dự án #' . $id . ' không còn tồn tại.');
        }
        return $p;
    }

    private static function load(int $articleId): array
    {
        $a = Article::find($articleId);
        if (!$a) {
            throw new \RuntimeException('Bài viết #' . $articleId . ' không còn tồn tại.');
        }
        return [$a, self::project((int)$a['project_id'])];
    }

    private static function outline(int $articleId, array $ctx): void
    {
        [$article, $project] = self::load($articleId);
        $claude = ClaudeService::fromSettings();
        $res = $claude->complete(ContentWriter::systemPrompt($project), ContentWriter::outlinePrompt($project, $article), 8000);
        Usage::log($ctx, 'anthropic', $res['model'], 'outline', $res['input_tokens'], $res['output_tokens'], 0, Usage::claudeCost($res['model'], $res['input_tokens'], $res['output_tokens']));

        $outline = ContentWriter::tag($res['text'], 'outline') ?? trim($res['text']);
        $data = ['outline' => $outline];
        $title = ContentWriter::tag($res['text'], 'title');
        if ($title && !$article['title']) {
            $data['title'] = mb_substr(strip_tags($title), 0, 500);
        }
        db()->update('articles', $data, 'id = ?', [$articleId]);
    }

    private static function write(int $articleId, array $ctx): void
    {
        [$article, $project] = self::load($articleId);
        Article::setStatus($articleId, 'writing');

        $links = InternalLinker::candidates((int)$project['id'], $article['keyword'], (string)$article['secondary_keywords'], 15, $article['wp_url']);
        $imageCount = max(0, min(6, (int)$project['images_per_article']));

        try {
            $res = ClaudeService::fromSettings()->complete(ContentWriter::systemPrompt($project), ContentWriter::writePrompt($project, $article, $links, $imageCount), 16000);
            Usage::log($ctx, 'anthropic', $res['model'], 'write', $res['input_tokens'], $res['output_tokens'], 0, Usage::claudeCost($res['model'], $res['input_tokens'], $res['output_tokens']));
            $parsed = ContentWriter::parseArticle($res['text']);
        } catch (\Throwable $e) {
            // Trả bài về trạng thái trước khi viết
            Article::setStatus($articleId, $article['status'] === 'writing' ? 'idea' : $article['status']);
            throw $e;
        }
        db()->update('articles', [
            'title' => $parsed['title'] ?: $article['title'],
            'slug' => $parsed['slug'],
            'meta_title' => $parsed['meta_title'],
            'meta_description' => $parsed['meta_description'],
            'excerpt' => $parsed['excerpt'],
            'content' => $parsed['content'],
        ], 'id = ?', [$articleId]);
        if ($parsed['tags']) {
            db()->update('articles', ['secondary_keywords' => $article['secondary_keywords'] ?: implode(', ', $parsed['tags'])], 'id = ?', [$articleId]);
        }

        // Lưu prompt ảnh (ảnh cũ chưa lên WP thì xóa đi để tạo lại)
        foreach (Article::images($articleId) as $old) {
            if ($old['file_path'] && is_file(BASE_PATH . '/' . $old['file_path'])) {
                @unlink(BASE_PATH . '/' . $old['file_path']);
            }
        }
        db()->query('DELETE FROM article_images WHERE article_id = ?', [$articleId]);
        foreach ($parsed['images'] as $pos => $img) {
            if ($pos > $imageCount) {
                continue;
            }
            db()->insert('article_images', ['article_id' => $articleId, 'position' => $pos, 'prompt' => $img['prompt'], 'alt_text' => $img['alt']]);
        }

        Article::setStatus($articleId, 'review');

        // Tự động tạo ảnh ngay sau khi viết xong nếu đã có OpenAI key.
        if (Settings::has('openai_api_key') && $parsed['images']) {
            try {
                self::images($articleId, $ctx, true);
            } catch (\Throwable $e) {
                throw new \RuntimeException('Đã viết xong bài, nhưng tạo ảnh bị lỗi (bấm "Tạo ảnh" để thử lại): ' . $e->getMessage());
            }
        }
    }

    private static function images(int $articleId, array $ctx, bool $onlyMissing): void
    {
        [$article, $project] = self::load($articleId);
        $images = Article::images($articleId);
        if (!$images) {
            throw new \RuntimeException('Bài chưa có prompt ảnh. Hãy viết bài bằng AI trước, hoặc thêm ảnh thủ công.');
        }
        $service = ImageService::fromSettings();
        $content = (string)$article['content'];
        $errors = [];
        foreach ($images as $img) {
            if ($onlyMissing && $img['file_path']) {
                continue;
            }
            try {
                $path = $service->generate((string)$img['prompt'], (int)$project['id'], $articleId . '-' . $img['position'] . '-' . (slugify((string)$article['keyword']) ?: 'img'));
            } catch (\Throwable $e) {
                $errors[] = 'Ảnh ' . $img['position'] . ': ' . $e->getMessage();
                continue;
            }
            Usage::log($ctx, 'openai', $service->model(), 'image', 0, 0, 1, (float)Settings::get('image_price_usd'));
            $content = self::placeImage($content, $img, $path);
            if ($img['file_path'] && $img['file_path'] !== $path && is_file(BASE_PATH . '/' . $img['file_path'])) {
                @unlink(BASE_PATH . '/' . $img['file_path']);
            }
            db()->update('article_images', ['file_path' => $path, 'wp_media_id' => null, 'wp_url' => null], 'id = ?', [$img['id']]);
        }
        db()->update('articles', ['content' => $content], 'id = ?', [$articleId]);
        if ($errors) {
            throw new \RuntimeException(implode(' | ', $errors));
        }
    }

    /** Thay placeholder [IMAGE_n] hoặc ảnh cũ trong bài bằng ảnh mới. */
    private static function placeImage(string $content, array $img, string $newPath): string
    {
        if ((int)$img['position'] === 0) {
            return $content; // ảnh đại diện không chèn vào thân bài
        }
        $newUrl = absolute_url($newPath);
        $figure = ContentWriter::figure($newUrl, (string)$img['alt_text']);
        $placeholder = '[IMAGE_' . $img['position'] . ']';
        if (str_contains($content, $placeholder)) {
            $content = preg_replace('~<p>\s*' . preg_quote($placeholder, '~') . '\s*</p>~', $figure, $content, 1) ?? $content;
            return str_replace($placeholder, $figure, $content);
        }
        foreach (array_filter([$img['file_path'] ? absolute_url($img['file_path']) : null, $img['wp_url']]) as $oldUrl) {
            if (str_contains($content, $oldUrl)) {
                return str_replace($oldUrl, $newUrl, $content);
            }
        }
        return $content . "\n" . $figure;
    }

    private static function regenerateImage(int $articleId, int $imageId, array $ctx, ?string $prompt): void
    {
        [$article, $project] = self::load($articleId);
        $img = db()->fetch('SELECT * FROM article_images WHERE id = ? AND article_id = ?', [$imageId, $articleId]);
        if (!$img) {
            throw new \RuntimeException('Ảnh không tồn tại.');
        }
        if ($prompt) {
            db()->update('article_images', ['prompt' => $prompt], 'id = ?', [$imageId]);
            $img['prompt'] = $prompt;
        }
        $service = ImageService::fromSettings();
        $path = $service->generate((string)$img['prompt'], (int)$project['id'], $articleId . '-' . $img['position'] . '-' . (slugify((string)$article['keyword']) ?: 'img'));
        Usage::log($ctx, 'openai', $service->model(), 'image', 0, 0, 1, (float)Settings::get('image_price_usd'));
        $content = self::placeImage((string)$article['content'], $img, $path);
        if ($img['file_path'] && is_file(BASE_PATH . '/' . $img['file_path'])) {
            @unlink(BASE_PATH . '/' . $img['file_path']);
        }
        db()->update('article_images', ['file_path' => $path, 'wp_media_id' => null, 'wp_url' => null], 'id = ?', [$imageId]);
        db()->update('articles', ['content' => $content], 'id = ?', [$articleId]);
    }

    private static function publish(int $articleId): void
    {
        [$article, $project] = self::load($articleId);
        if (trim((string)$article['content']) === '') {
            throw new \RuntimeException('Bài chưa có nội dung.');
        }
        $wp = Project::wordpress($project);
        $content = (string)$article['content'];
        $featuredId = null;

        foreach (Article::images($articleId) as $img) {
            if (!$img['file_path'] || !is_file(BASE_PATH . '/' . $img['file_path'])) {
                continue;
            }
            if (!$img['wp_media_id']) {
                $ext = pathinfo($img['file_path'], PATHINFO_EXTENSION);
                $fileName = (slugify((string)($img['alt_text'] ?: $article['keyword'])) ?: 'anh') . '-' . $img['position'] . '.' . $ext;
                [$mediaId, $mediaUrl] = $wp->uploadMedia(BASE_PATH . '/' . $img['file_path'], $fileName, (string)$img['alt_text'], (string)($img['alt_text'] ?: $article['title']));
                db()->update('article_images', ['wp_media_id' => $mediaId, 'wp_url' => $mediaUrl], 'id = ?', [$img['id']]);
                $img['wp_media_id'] = $mediaId;
                $img['wp_url'] = $mediaUrl;
            }
            $content = str_replace(absolute_url($img['file_path']), (string)$img['wp_url'], $content);
            if ((int)$img['position'] === 0) {
                $featuredId = (int)$img['wp_media_id'];
            }
        }
        // Xóa placeholder ảnh còn sót
        $content = preg_replace('~<p>\s*\[IMAGE_\d+\]\s*</p>|\[IMAGE_\d+\]~', '', $content) ?? $content;

        $status = in_array($project['wp_default_status'], ['draft', 'publish', 'pending'], true) ? $project['wp_default_status'] : 'draft';
        $data = [
            'title' => $article['title'] ?: $article['keyword'],
            'content' => $content,
            'status' => $status,
            'excerpt' => (string)($article['meta_description'] ?: $article['excerpt']),
        ];
        if ($article['slug']) {
            $data['slug'] = $article['slug'];
        }
        $categoryId = (int)($article['wp_category_id'] ?: $project['wp_default_category_id']);
        if ($categoryId > 0) {
            $data['categories'] = [$categoryId];
        }
        if ($featuredId) {
            $data['featured_media'] = $featuredId;
        }
        // Meta SEO cho Yoast / Rank Math (chỉ có tác dụng nếu site cho phép ghi meta qua REST)
        if ($article['meta_title'] || $article['meta_description']) {
            $data['meta'] = array_filter([
                'rank_math_title' => $article['meta_title'],
                'rank_math_description' => $article['meta_description'],
                'rank_math_focus_keyword' => $article['keyword'],
                '_yoast_wpseo_title' => $article['meta_title'],
                '_yoast_wpseo_metadesc' => $article['meta_description'],
                '_yoast_wpseo_focuskw' => $article['keyword'],
            ]);
        }

        try {
            $post = $wp->savePost($article['wp_post_id'] ? (int)$article['wp_post_id'] : null, $data);
        } catch (\RuntimeException $e) {
            if (!isset($data['meta'])) {
                throw $e;
            }
            unset($data['meta']); // site chặn ghi meta -> đăng lại không kèm meta
            $post = $wp->savePost($article['wp_post_id'] ? (int)$article['wp_post_id'] : null, $data);
        }

        db()->update('articles', [
            'wp_post_id' => (int)$post['id'],
            'wp_url' => (string)($post['link'] ?? ''),
            'published_at' => $status === 'publish' ? now() : $article['published_at'],
        ], 'id = ?', [$articleId]);
        Article::setStatus($articleId, $status === 'publish' ? 'published' : 'wp_draft');
        // Cập nhật lại link bài trên sheet (kể cả khi trạng thái không đổi)
        Article::syncToSheet($articleId);

        if ($status === 'publish' && !empty($post['link'])) {
            $url = (string)$post['link'];
            db()->query(
                'INSERT IGNORE INTO internal_links (project_id, url, url_hash, title, keywords, source, title_fetched) VALUES (?, ?, ?, ?, ?, ?, 1)',
                [$project['id'], $url, sha1($url), $article['title'], $article['keyword'], 'wordpress']
            );
        }
    }

    private static function importSitemap(int $projectId): void
    {
        $project = self::project($projectId);
        if (empty($project['sitemap_url'])) {
            throw new \RuntimeException('Dự án chưa nhập link sitemap.');
        }
        $urls = SitemapService::collect($project['sitemap_url']);
        foreach ($urls as $url) {
            db()->query(
                'INSERT IGNORE INTO internal_links (project_id, url, url_hash, title, source, title_fetched) VALUES (?, ?, ?, ?, ?, 0)',
                [$projectId, mb_substr($url, 0, 1000), sha1($url), SitemapService::titleFromUrl($url), 'sitemap']
            );
        }
        if (!Queue::projectHasPending($projectId, 'fetch_titles')) {
            Queue::push('fetch_titles', [], ['project_id' => $projectId]);
        }
    }

    private static function importWordPress(int $projectId): void
    {
        $project = self::project($projectId);
        foreach (Project::wordpress($project)->publishedContent() as $item) {
            db()->query(
                'INSERT INTO internal_links (project_id, url, url_hash, title, source, title_fetched) VALUES (?, ?, ?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE title = VALUES(title), title_fetched = 1',
                [$projectId, mb_substr($item['url'], 0, 1000), sha1($item['url']), mb_substr($item['title'], 0, 500), 'wordpress']
            );
        }
    }

    /** Lấy tiêu đề thật cho các URL từ sitemap, mỗi lượt 25 URL. */
    private static function fetchTitles(int $projectId): void
    {
        $rows = db()->fetchAll('SELECT id, url FROM internal_links WHERE project_id = ? AND title_fetched = 0 LIMIT 25', [$projectId]);
        foreach ($rows as $row) {
            $title = SitemapService::fetchTitle($row['url']);
            $data = ['title_fetched' => 1];
            if ($title) {
                $data['title'] = $title;
            }
            db()->update('internal_links', $data, 'id = ?', [$row['id']]);
        }
        if (count($rows) === 25) {
            Queue::push('fetch_titles', [], ['project_id' => $projectId]);
        }
    }

    private static function sheetPush(int $articleId): void
    {
        [$article, $project] = self::load($articleId);
        if (Project::hasSheet($project)) {
            SheetSync::pushArticle($project, $article);
        }
    }
}
