<?php
declare(strict_types=1);

namespace App;

use App\Models\Article;

/** Hàng đợi công việc chạy nền (lưu trong bảng jobs, cron/worker.php xử lý). */
class Queue
{
    public const AI_TASKS = ['outline', 'write', 'images', 'image'];

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

    public static function projectHasPending(int $projectId, string $type): bool
    {
        return (bool)db()->value(
            "SELECT 1 FROM jobs WHERE project_id = ? AND type = ? AND status IN ('pending','running') LIMIT 1",
            [$projectId, $type]
        );
    }
}
