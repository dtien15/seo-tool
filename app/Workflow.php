<?php
declare(strict_types=1);

namespace App;

use App\Models\Article;

/**
 * Luồng xử lý bài viết theo quy trình SEO:
 *   Kế hoạch → Outline → TP duyệt outline → Viết bài → SEO + TP duyệt bài
 *   → Làm hình → SEO + TP duyệt hình → Sẵn sàng đăng → Đã đăng → (định kỳ) tối ưu lại
 */
class Workflow
{
    /**
     * action => [trạng thái hiện tại hợp lệ, trạng thái tiếp theo, vai trò được làm, nhãn nút, màu nút]
     * Vai trò 'admin' luôn được phép.
     */
    public const ACTIONS = [
        'start_outline'   => [['plan'], 'outline', ['seo'], 'Bắt đầu làm outline', 'outline-primary'],
        'submit_outline'  => [['plan', 'outline'], 'outline_review', ['seo'], 'Gửi TP duyệt outline', 'primary'],
        'approve_outline' => [['outline_review'], 'writing', ['leader'], 'Duyệt outline', 'success'],
        'reject_outline'  => [['outline_review'], 'outline', ['leader'], 'Yêu cầu sửa outline', 'outline-danger'],
        'submit_content'  => [['writing', 'revise'], 'content_review', ['content', 'seo'], 'Gửi duyệt bài', 'primary'],
        'approve_content' => [['content_review'], 'design', ['seo', 'leader'], 'Duyệt bài', 'success'],
        'reject_content'  => [['content_review'], 'revise', ['seo', 'leader'], 'Yêu cầu sửa bài', 'outline-danger'],
        'submit_images'   => [['design', 'image_revise'], 'image_review', ['design', 'seo'], 'Gửi duyệt hình', 'primary'],
        'approve_images'  => [['image_review'], 'ready', ['seo', 'leader'], 'Duyệt hình', 'success'],
        'reject_images'   => [['image_review'], 'image_revise', ['seo', 'leader'], 'Yêu cầu sửa hình', 'outline-danger'],
        'mark_reviewed'   => [['published', 'wp_draft'], null, ['seo'], 'Đã kiểm tra, chưa cần sửa', 'outline-success'],
        'reoptimize'      => [['published', 'wp_draft'], 'writing', ['seo'], 'Tối ưu lại bài này', 'outline-warning'],
    ];

    /** Giai đoạn duyệt và các phía cần duyệt. */
    private const STAGES = [
        'approve_outline' => ['outline', ['leader']],
        'approve_content' => ['content', ['seo', 'leader']],
        'approve_images'  => ['image', ['seo', 'leader']],
    ];

    private const SIDE_LABELS = ['seo' => 'SEO', 'leader' => 'TP'];

    public static function can(string $action, array $article, ?array $user = null): bool
    {
        $user ??= Auth::user();
        [$from, , $roles] = self::ACTIONS[$action] ?? [[], null, []];
        if (!in_array($article['status'], $from, true)) {
            return false;
        }
        return $user['role'] === 'admin' || in_array($user['role'], $roles, true);
    }

    /** Các phía đã duyệt ở giai đoạn hiện tại: ['seo' => 'Tên', 'leader' => 'Tên'] */
    public static function approvals(array $article, string $stage): array
    {
        $rows = db()->fetchAll(
            'SELECT a.side, u.name FROM article_approvals a JOIN users u ON u.id = a.user_id WHERE a.article_id = ? AND a.stage = ?',
            [$article['id'], $stage]
        );
        $out = [];
        foreach ($rows as $r) {
            $out[$r['side']] = $r['name'];
        }
        return $out;
    }

    /**
     * Danh sách nút thao tác cho người dùng hiện tại.
     * @return list<array{action: string, label: string, color: string, side?: string, needs_note: bool}>
     */
    public static function buttons(array $article, ?array $user = null): array
    {
        $user ??= Auth::user();
        $buttons = [];
        foreach (self::ACTIONS as $action => [, , , $label, $color]) {
            if (!self::can($action, $article, $user)) {
                continue;
            }
            $needsNote = str_starts_with($action, 'reject_') || $action === 'reoptimize';
            if (isset(self::STAGES[$action])) {
                [$stage, $sides] = self::STAGES[$action];
                $done = self::approvals($article, $stage);
                foreach (self::sidesFor($user, $sides) as $side) {
                    if (!isset($done[$side])) {
                        $buttons[] = ['action' => $action, 'label' => $label . (count($sides) > 1 ? ' (' . self::SIDE_LABELS[$side] . ')' : ''), 'color' => $color, 'side' => $side, 'needs_note' => false];
                    }
                }
                continue;
            }
            $buttons[] = ['action' => $action, 'label' => $label, 'color' => $color, 'needs_note' => $needsNote];
        }
        return $buttons;
    }

    /** Phía duyệt mà người dùng đại diện (admin đại diện được cả hai). */
    private static function sidesFor(array $user, array $sides): array
    {
        if ($user['role'] === 'admin') {
            return $sides;
        }
        return in_array($user['role'], $sides, true) ? [$user['role']] : [];
    }

    /** Thực hiện một bước. Trả về thông báo cho người dùng. */
    public static function apply(string $action, int $articleId, ?string $note = null, ?string $side = null): string
    {
        $user = Auth::user();
        $article = Article::find($articleId) ?? throw new \RuntimeException('Bài viết không tồn tại.');
        if (!isset(self::ACTIONS[$action])) {
            throw new \RuntimeException('Thao tác không hợp lệ.');
        }
        if (!self::can($action, $article, $user)) {
            throw new \RuntimeException('Bạn không thể "' . self::ACTIONS[$action][3] . '" khi bài đang ở trạng thái "' . status_label($article['status']) . '".');
        }
        if (in_array($article['ai_state'], ['queued', 'running'], true)) {
            throw new \RuntimeException('AI đang xử lý bài này, vui lòng đợi xong.');
        }
        $note = trim((string)$note) ?: null;
        [, $to, , $label] = self::ACTIONS[$action];

        // Kiểm tra điều kiện trước khi gửi duyệt
        match ($action) {
            'submit_outline' => trim((string)$article['outline']) === '' ? throw new \RuntimeException('Chưa có outline để gửi duyệt.') : null,
            'submit_content' => mb_strlen(trim(strip_tags((string)$article['content']))) < 100 ? throw new \RuntimeException('Bài chưa có nội dung để gửi duyệt.') : null,
            'submit_images' => !db()->value('SELECT 1 FROM article_images WHERE article_id = ? AND position = 0 AND file_path IS NOT NULL', [$articleId])
                ? throw new \RuntimeException('Cần có ít nhất ảnh đại diện trước khi gửi duyệt hình.') : null,
            default => null,
        };
        if ((str_starts_with($action, 'reject_') || $action === 'reoptimize') && !$note) {
            throw new \RuntimeException('Vui lòng ghi rõ nội dung cần sửa.');
        }

        // Duyệt: ghi nhận từng phía, đủ cả hai phía mới chuyển bước
        if (isset(self::STAGES[$action])) {
            [$stage, $sides] = self::STAGES[$action];
            $mySides = self::sidesFor($user, $sides);
            $side = $side && in_array($side, $mySides, true) ? $side : ($mySides[0] ?? null);
            if (!$side) {
                throw new \RuntimeException('Bạn không có quyền duyệt bước này.');
            }
            $done = self::approvals($article, $stage);
            if (!isset($done[$side])) {
                db()->insert('article_approvals', ['article_id' => $articleId, 'stage' => $stage, 'side' => $side, 'user_id' => $user['id']]);
                $done[$side] = $user['name'];
            }
            Article::log($articleId, $action, (count($sides) > 1 ? self::SIDE_LABELS[$side] . ' đã duyệt' : 'Đã duyệt') . ($note ? ': ' . $note : ''));
            $missing = array_diff($sides, array_keys($done));
            if ($missing) {
                return 'Đã duyệt phía ' . self::SIDE_LABELS[$side] . '. Đang chờ ' . implode(', ', array_map(fn($s) => self::SIDE_LABELS[$s], $missing)) . ' duyệt.';
            }
            db()->update('articles', ['reject_note' => null], 'id = ?', [$articleId]);
            Article::setStatus($articleId, $to);
            return 'Đã duyệt đủ. Bài chuyển sang "' . status_label($to) . '".';
        }

        $data = [];
        if (str_starts_with($action, 'submit_')) {
            $stage = ['submit_outline' => 'outline', 'submit_content' => 'content', 'submit_images' => 'image'][$action];
            db()->query('DELETE FROM article_approvals WHERE article_id = ? AND stage = ?', [$articleId, $stage]);
            $data['submitted_at'] = now();
        }
        if (str_starts_with($action, 'reject_')) {
            $data['reject_note'] = $note;
        }
        if ($action === 'mark_reviewed' || $action === 'reoptimize') {
            $data['last_reviewed_at'] = now();
        }
        if ($action === 'reoptimize') {
            $data['reject_note'] = $note;
        }
        if ($data) {
            db()->update('articles', $data, 'id = ?', [$articleId]);
        }
        Article::log($articleId, $action, $note);
        if ($to !== null) {
            Article::setStatus($articleId, $to);
            return $label . ': bài chuyển sang "' . status_label($to) . '".';
        }
        return $label . '.';
    }

    /** Nhãn hiển thị cho lịch sử. */
    public static function actionLabel(string $action): string
    {
        $extra = [
            'status' => 'Đổi trạng thái', 'created' => 'Tạo bài', 'ai_outline' => 'AI tạo outline', 'ai_write' => 'AI viết bài',
            'ai_images' => 'AI tạo hình', 'upload_image' => 'Upload hình', 'publish' => 'Đăng WordPress', 'assign' => 'Giao việc',
            'wp_import' => 'Lấy nội dung từ WordPress', 'manual_outline' => 'Dán outline từ ChatGPT / Claude',
            'manual_write' => 'Dán bài viết từ ChatGPT / Claude',
        ];
        return self::ACTIONS[$action][3] ?? $extra[$action] ?? $action;
    }

    /** Bài đã đăng quá số ngày quy định chưa được kiểm tra lại. */
    public static function dueForReview(int $projectId, int $days): string
    {
        return "a.project_id = $projectId AND a.status IN ('published','wp_draft')
                AND COALESCE(a.last_reviewed_at, a.published_at, a.updated_at) < NOW() - INTERVAL $days DAY";
    }
}
