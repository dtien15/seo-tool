<?php
declare(strict_types=1);

namespace App;

use App\Models\Article;

/** Hàng đợi công việc chạy nền (lưu trong bảng jobs, cron/worker.php xử lý). */
class Queue
{
    public const AI_TASKS = ['outline', 'write', 'images', 'image'];

    /** task => [các bước được phép, vai trò được phép (admin luôn được), mô tả] */
    public const TASK_RULES = [
        'outline' => [['plan', 'outline'], ['seo', 'leader'], 'tạo outline'],
        'write'   => [['writing', 'revise'], ['content', 'seo'], 'viết bài bằng AI'],
        'images'  => [['design', 'image_revise'], ['design', 'seo'], 'tạo hình bằng AI'],
        'image'   => [['design', 'image_revise'], ['design', 'seo'], 'tạo lại hình'],
        'publish' => [['ready', 'wp_draft', 'published'], ['seo', 'leader'], 'đăng WordPress'],
    ];

    public static function push(string $type, array $payload = [], array $ctx = [], int $delaySeconds = 0): int
    {
        return db()->insert('jobs', [
            'type' => $type,
            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'user_id' => $ctx['user_id'] ?? null,
            'project_id' => $ctx['project_id'] ?? null,
            'article_id' => $ctx['article_id'] ?? null,
            'available_at' => date('Y-m-d H:i:s', time() + $delaySeconds),
        ]);
    }

    /**
     * Đưa một tác vụ của bài viết vào hàng đợi (outline / write / images / image / publish).
     * Kiểm tra trùng và hạn mức chi phí trước khi thêm.
     */
    public static function articleTask(string $task, int $articleId, ?int $userId, array $payload = []): void
    {
        $article = Article::find($articleId);
        if (!$article) {
            throw new \RuntimeException('Bài viết không tồn tại.');
        }
        self::assertAllowed($task, $article, $userId);
        if (in_array($article['ai_state'], ['queued', 'running'], true)) {
            throw new \RuntimeException('Bài "' . $article['keyword'] . '" đang có tác vụ chạy, vui lòng đợi.');
        }
        if ($userId && in_array($task, self::AI_TASKS, true)) {
            Usage::assertWithinBudget($userId);
        }
        self::push($task, ['article_id' => $articleId] + $payload, [
            'user_id' => $userId,
            'project_id' => $article['project_id'],
            'article_id' => $articleId,
        ]);
        Article::setAiState($articleId, 'queued', $task);
    }

    /** Tác vụ chỉ làm được ở đúng bước của quy trình, đúng vai trò. */
    public static function assertAllowed(string $task, array $article, ?int $userId): void
    {
        [$statuses, $roles, $label] = self::TASK_RULES[$task] ?? [[], [], $task];
        $user = $userId ? db()->fetch('SELECT role FROM users WHERE id = ?', [$userId]) : null;
        $isAdmin = ($user['role'] ?? '') === 'admin';
        if (!$isAdmin && !in_array($article['status'], $statuses, true)) {
            throw new \RuntimeException('Không thể ' . $label . ' khi bài đang ở bước "' . status_label($article['status']) . '".');
        }
        if ($user && !$isAdmin && !in_array($user['role'], $roles, true)) {
            throw new \RuntimeException('Vai trò ' . role_label($user['role']) . ' không ' . $label . ' được.');
        }
    }

    public static function projectHasPending(int $projectId, string $type): bool
    {
        return (bool)db()->value(
            "SELECT 1 FROM jobs WHERE project_id = ? AND type = ? AND status IN ('pending','running') LIMIT 1",
            [$projectId, $type]
        );
    }
}
