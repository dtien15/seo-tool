<?php
declare(strict_types=1);

namespace App;

/** Ghi nhận chi phí API và kiểm tra hạn mức theo tháng. */
class Usage
{
    public static function log(array $ctx, string $provider, string $model, string $task, int $in, int $out, int $images, float $cost): void
    {
        db()->insert('usage_logs', [
            'user_id' => $ctx['user_id'] ?? null,
            'project_id' => $ctx['project_id'] ?? null,
            'article_id' => $ctx['article_id'] ?? null,
            'provider' => $provider,
            'model' => $model,
            'task' => $task,
            'input_tokens' => $in,
            'output_tokens' => $out,
            'images' => $images,
            'cost_usd' => round($cost, 5),
        ]);
    }

    public static function claudeCost(string $model, int $in, int $out): float
    {
        [, $priceIn, $priceOut] = Settings::CLAUDE_MODELS[$model] ?? ['', 4.0, 20.0];
        return $in / 1_000_000 * $priceIn + $out / 1_000_000 * $priceOut;
    }

    public static function monthSpent(int $userId): float
    {
        return (float)db()->value(
            'SELECT COALESCE(SUM(cost_usd),0) FROM usage_logs WHERE user_id = ? AND created_at >= ?',
            [$userId, date('Y-m-01 00:00:00')]
        );
    }

    public static function budgetFor(array $user): ?float
    {
        if ($user['monthly_budget_usd'] !== null && $user['monthly_budget_usd'] !== '') {
            return (float)$user['monthly_budget_usd'];
        }
        $default = Settings::get('default_monthly_budget', '');
        return $default === '' || $default === null ? null : (float)$default;
    }

    public static function assertWithinBudget(int $userId): void
    {
        $user = db()->fetch('SELECT * FROM users WHERE id = ?', [$userId]);
        if (!$user || $user['role'] === 'admin') {
            return;
        }
        $budget = self::budgetFor($user);
        if ($budget !== null && self::monthSpent($userId) >= $budget) {
            throw new \RuntimeException('Bạn đã dùng hết hạn mức chi phí AI tháng này (' . money($budget) . '). Liên hệ quản trị viên.');
        }
    }
}
