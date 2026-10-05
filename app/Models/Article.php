<?php
declare(strict_types=1);

namespace App\Models;

use App\Queue;
use App\Services\SheetSync;

class Article
{
    public static function find(int $id): ?array
    {
        return db()->fetch('SELECT * FROM articles WHERE id = ?', [$id]);
    }

    /** @return array{0: array, 1: array} [article, project] */
    public static function findOrFail(int $id): array
    {
        $article = self::find($id);
        if (!$article) {
            abort(404, 'Bài viết không tồn tại.');
        }
        $project = Project::findOrFail((int)$article['project_id']);
        $user = \App\Auth::user();
        $field = ['content' => 'writer_id', 'design' => 'designer_id'][$user['role']] ?? null;
        if ($field && (int)$article[$field] !== (int)$user['id']) {
            abort(403, 'Bài viết này chưa được giao cho bạn.');
        }
        return [$article, $project];
    }

    public static function images(int $articleId): array
    {
        return db()->fetchAll('SELECT * FROM article_images WHERE article_id = ? ORDER BY position', [$articleId]);
    }

    public static function create(array $project, array $data): int
    {
        return db()->insert('articles', [
            'project_id' => $project['id'],
            'assigned_to' => $data['assigned_to'] ?? null,
            'keyword' => mb_substr($data['keyword'], 0, 255),
            'secondary_keywords' => $data['secondary_keywords'] ?? null,
            'notes' => $data['notes'] ?? null,
            'word_count' => $data['word_count'] ?? null,
            'title' => $data['title'] ?? null,
            'status' => $data['status'] ?? 'plan',
            'type' => $data['type'] ?? 'new',
            'writer_id' => $data['writer_id'] ?? null,
            'designer_id' => $data['designer_id'] ?? null,
            'cluster' => $data['cluster'] ?? null,
            'planned_date' => $data['planned_date'] ?? null,
            'wp_url' => $data['wp_url'] ?? null,
            'status_changed_at' => now(),
        ]);
    }

    /**
     * Đổi trạng thái bài viết.
     * $fromSheet = true khi thay đổi đến từ Google Sheet (không đẩy ngược lại sheet).
     */
    public static function setStatus(int $id, string $status, bool $fromSheet = false, ?string $changedAt = null): void
    {
        if (!isset(article_statuses()[$status])) {
            return;
        }
        $article = self::find($id);
        if (!$article || $article['status'] === $status) {
            return;
        }
        db()->update('articles', ['status' => $status, 'status_changed_at' => $changedAt ?? now()], 'id = ?', [$id]);

        $project = Project::find((int)$article['project_id']);
        if (!$project) {
            return;
        }
        if (!$fromSheet) {
            self::syncToSheet($id);
        }
        if ($fromSheet) {
            self::log($id, 'status', 'Đổi trạng thái trên Google Sheet: ' . status_label($article['status']) . ' → ' . status_label($status), null);
        }
        if ($status === 'ready' && $project['auto_publish'] && $article['content']) {
            try {
                Queue::articleTask('publish', $id, null);
            } catch (\RuntimeException $e) {
                log_error('Auto publish #' . $id . ': ' . $e->getMessage());
            }
        }
    }

    /** Đẩy một bài lên Google Sheet ngay; nếu lỗi thì đưa vào hàng đợi để thử lại. */
    public static function syncToSheet(int $id): void
    {
        $article = self::find($id);
        if (!$article) {
            return;
        }
        $project = Project::find((int)$article['project_id']);
        if (!$project || !Project::hasSheet($project)) {
            return;
        }
        try {
            SheetSync::pushArticle($project, $article);
        } catch (\Throwable $e) {
            log_error('Sheet push #' . $id . ': ' . $e->getMessage());
            Queue::push('sheet_push', ['article_id' => $id], ['project_id' => $project['id'], 'article_id' => $id]);
        }
    }

    /** Ghi lịch sử xử lý bài viết. */
    public static function log(int $id, string $action, ?string $note = null, ?int $userId = -1): void
    {
        db()->insert('article_logs', [
            'article_id' => $id,
            'user_id' => $userId === -1 ? \App\Auth::id() : $userId,
            'action' => $action,
            'note' => $note,
        ]);
    }

    public static function logs(int $id): array
    {
        return db()->fetchAll(
            'SELECT l.*, u.name AS user_name, u.role AS user_role FROM article_logs l LEFT JOIN users u ON u.id = l.user_id
             WHERE l.article_id = ? ORDER BY l.id DESC LIMIT 100',
            [$id]
        );
    }

    public static function delete(int $id): void
    {
        foreach (self::images($id) as $img) {
            if ($img['file_path'] && is_file(BASE_PATH . '/' . $img['file_path'])) {
                @unlink(BASE_PATH . '/' . $img['file_path']);
            }
        }
        db()->query('DELETE FROM article_images WHERE article_id = ?', [$id]);
        db()->query('DELETE FROM article_logs WHERE article_id = ?', [$id]);
        db()->query('DELETE FROM article_approvals WHERE article_id = ?', [$id]);
        db()->query('UPDATE keywords SET article_id = NULL WHERE article_id = ?', [$id]);
        db()->query('DELETE FROM articles WHERE id = ?', [$id]);
    }

    public static function setAiState(int $id, string $state, ?string $task = null, ?string $error = null): void
    {
        $data = ['ai_state' => $state, 'ai_error' => $error];
        if ($task !== null) {
            $data['ai_task'] = $task;
        }
        db()->update('articles', $data, 'id = ?', [$id]);
    }
}
