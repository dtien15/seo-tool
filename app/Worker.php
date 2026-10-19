<?php
declare(strict_types=1);

namespace App;

use App\Models\Article;
use App\Services\SheetSync;

/** Bộ xử lý hàng đợi – chạy bởi cron mỗi phút. */
class Worker
{
    private const MAX_CONCURRENT = 3;       // số worker chạy song song tối đa
    private const RUN_SECONDS = 50;         // không nhận job mới sau 50 giây
    private const STALE_MINUTES = 20;       // job "running" quá lâu coi như treo

    private string $id;
    private ?array $current = null;         // job đang xử lý (để ghi lỗi nếu PHP chết giữa chừng)

    public function __construct()
    {
        $this->id = gethostname() . ':' . getmypid() . ':' . substr(random_token(4), 0, 6);
    }

    public function run(): void
    {
        register_shutdown_function([$this, 'onShutdown']);
        $this->recoverStale();
        $this->scheduleSheetPulls();

        $running = (int)db()->value(
            "SELECT COUNT(DISTINCT locked_by) FROM jobs WHERE status = 'running' AND locked_at > NOW() - INTERVAL " . self::STALE_MINUTES . ' MINUTE'
        );
        if ($running >= self::MAX_CONCURRENT) {
            $this->out('Đã đủ ' . $running . ' worker đang chạy, thoát.');
            return;
        }

        $this->out('Worker bắt đầu (PHP ' . PHP_VERSION . ', RAM ' . ini_get('memory_limit') . ', time limit ' . ini_get('max_execution_time') . ')');
        $start = time();
        while (time() - $start < self::RUN_SECONDS) {
            $job = $this->claim();
            if (!$job) {
                break;
            }
            $this->process($job);
        }
    }

    private function claim(): ?array
    {
        $claimed = db()->query(
            "UPDATE jobs SET status = 'running', locked_by = ?, locked_at = NOW(), attempts = attempts + 1
             WHERE status = 'pending' AND available_at <= NOW() ORDER BY id LIMIT 1",
            [$this->id]
        )->rowCount();
        if (!$claimed) {
            return null;
        }
        return db()->fetch("SELECT * FROM jobs WHERE locked_by = ? AND status = 'running' ORDER BY locked_at DESC, id DESC LIMIT 1", [$this->id]);
    }

    private function process(array $job): void
    {
        $this->out("Job #{$job['id']} {$job['type']}...");
        $this->current = $job;
        $isArticleTask = Jobs::isArticleTask($job['type']);
        if ($isArticleTask && $job['article_id']) {
            Article::setAiState((int)$job['article_id'], 'running', $job['type']);
        }
        try {
            Jobs::handle($job);
            db()->update('jobs', ['status' => 'done', 'finished_at' => now(), 'last_error' => null], 'id = ?', [$job['id']]);
            if ($isArticleTask && $job['article_id']) {
                Article::setAiState((int)$job['article_id'], 'idle', $job['type']);
            }
            $this->out('  xong.');
        } catch (\Throwable $e) {
            $message = mb_substr($e->getMessage(), 0, 2000);
            log_error("Job #{$job['id']} {$job['type']}: " . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            // Tác vụ AI tốn tiền -> không tự thử lại; tác vụ khác thử lại tối đa 3 lần.
            $retry = !$isArticleTask && $job['type'] !== 'cluster_keywords' && !str_starts_with($job['type'], 'ai_') && (int)$job['attempts'] < 3;
            db()->update('jobs', [
                'status' => $retry ? 'pending' : 'failed',
                'last_error' => $message,
                'available_at' => date('Y-m-d H:i:s', time() + 60 * (int)$job['attempts']),
                'finished_at' => $retry ? null : now(),
            ], 'id = ?', [$job['id']]);
            if ($isArticleTask && $job['article_id']) {
                Article::setAiState((int)$job['article_id'], 'failed', $job['type'], $message);
            }
            $this->out('  LỖI: ' . $message);
        }
        $this->current = null;
    }

    /** PHP chết giữa chừng (lỗi nghiêm trọng, hết bộ nhớ...) -> ghi lỗi vào job thay vì để treo. */
    public function onShutdown(): void
    {
        $err = error_get_last();
        if (!$this->current || !$err || !in_array($err['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR], true)) {
            return;
        }
        $job = $this->current;
        $message = 'Lỗi PHP: ' . substr($err['message'], 0, 1500) . ' (' . basename($err['file']) . ':' . $err['line'] . ')';
        log_error("Job #{$job['id']} {$job['type']}: " . $message);
        try {
            db()->update('jobs', ['status' => 'failed', 'last_error' => $message, 'finished_at' => now()], 'id = ?', [$job['id']]);
            if (Jobs::isArticleTask($job['type']) && $job['article_id']) {
                Article::setAiState((int)$job['article_id'], 'failed', $job['type'], $message);
            }
        } catch (\Throwable) {
        }
    }

    private function recoverStale(): void
    {
        $stale = db()->fetchAll(
            "SELECT * FROM jobs WHERE status = 'running' AND locked_at < NOW() - INTERVAL " . self::STALE_MINUTES . ' MINUTE'
        );
        foreach ($stale as $job) {
            db()->update('jobs', ['status' => 'failed', 'last_error' => 'Quá thời gian xử lý (có thể hosting đã dừng tiến trình).', 'finished_at' => now()], 'id = ?', [$job['id']]);
            if (Jobs::isArticleTask($job['type']) && $job['article_id']) {
                Article::setAiState((int)$job['article_id'], 'failed', $job['type'], 'Quá thời gian xử lý, vui lòng thử lại.');
            }
        }
        // Dọn job cũ đã xong hơn 30 ngày
        db()->query("DELETE FROM jobs WHERE status = 'done' AND finished_at < NOW() - INTERVAL 30 DAY");
    }

    /** Mỗi 5 phút đọc lại Google Sheet của từng dự án (dự phòng khi webhook lỗi). */
    private function scheduleSheetPulls(): void
    {
        if (!Settings::has('google_service_account')) {
            return;
        }
        $projects = db()->fetchAll(
            "SELECT id FROM projects WHERE gsheet_id IS NOT NULL AND gsheet_id <> ''
             AND (sheet_pulled_at IS NULL OR sheet_pulled_at < NOW() - INTERVAL 5 MINUTE)"
        );
        foreach ($projects as $p) {
            if (!Queue::projectHasPending((int)$p['id'], 'sheet_pull')) {
                Queue::push('sheet_pull', [], ['project_id' => $p['id']]);
                db()->update('projects', ['sheet_pulled_at' => now()], 'id = ?', [$p['id']]);
            }
        }
    }

    /** In ra màn hình và ghi nhật ký worker (xem ở trang Hàng đợi). */
    public static function log(string $message): void
    {
        $line = '[' . date('Y-m-d H:i:s') . '] [' . getmypid() . '] ' . $message;
        if (PHP_SAPI === 'cli') {
            echo $line . PHP_EOL;
        }
        $dir = BASE_PATH . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        @file_put_contents($dir . '/worker.log', $line . PHP_EOL, FILE_APPEND);
    }

    /** N dòng cuối của nhật ký worker. */
    public static function tail(int $lines = 40): array
    {
        $file = BASE_PATH . '/storage/logs/worker.log';
        if (!is_file($file)) {
            return [];
        }
        if (filesize($file) > 2_000_000) {
            @rename($file, $file . '.old');
            return [];
        }
        return array_slice(file($file, FILE_IGNORE_NEW_LINES) ?: [], -$lines);
    }

    private function out(string $message): void
    {
        self::log($message);
    }
}
