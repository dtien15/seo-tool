<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Article;
use App\Services\ClaudeService;
use App\Services\GoogleSheetsService;
use App\Settings;

/** Cài đặt hệ thống (API key...), thống kê chi phí, hàng đợi – chỉ admin. */
class SettingsController
{
    public function index(): void
    {
        Auth::requireAdmin();
        $lastJob = db()->value("SELECT MAX(finished_at) FROM jobs WHERE status IN ('done','failed')");
        view('settings', [
            'pageTitle' => 'Cài đặt hệ thống',
            'hasClaude' => Settings::has('anthropic_api_key'),
            'hasOpenAI' => Settings::has('openai_api_key'),
            'serviceEmail' => GoogleSheetsService::serviceEmail(),
            'sdkInstalled' => class_exists(\Anthropic\Client::class),
            'lastJob' => $lastJob,
            'pendingJobs' => (int)db()->value("SELECT COUNT(*) FROM jobs WHERE status = 'pending'"),
        ]);
    }

    public function save(): void
    {
        Auth::requireAdmin();
        foreach (['anthropic_api_key', 'openai_api_key'] as $k) {
            $v = trim((string)($_POST[$k] ?? ''));
            if ($v !== '') {
                Settings::set($k, $v);
            }
            if (input('clear_' . $k)) {
                Settings::set($k, null);
            }
        }
        $json = trim((string)($_POST['google_service_account'] ?? ''));
        if (!empty($_FILES['google_service_account_file']['tmp_name']) && is_uploaded_file($_FILES['google_service_account_file']['tmp_name'])) {
            $json = (string)file_get_contents($_FILES['google_service_account_file']['tmp_name']);
        }
        if ($json !== '') {
            $data = json_decode($json, true);
            if (!is_array($data) || empty($data['client_email']) || empty($data['private_key'])) {
                throw new \RuntimeException('File JSON Service Account không hợp lệ.');
            }
            Settings::set('google_service_account', $json);
        }

        $model = (string)input('claude_model');
        if (isset(Settings::CLAUDE_MODELS[$model])) {
            Settings::set('claude_model', $model);
        }
        if (in_array(input('claude_effort'), ['low', 'medium', 'high'], true)) {
            Settings::set('claude_effort', (string)input('claude_effort'));
        }
        foreach (['image_model', 'image_size', 'image_quality'] as $k) {
            $v = trim((string)input($k, ''));
            if ($v !== '') {
                Settings::set($k, $v);
            }
        }
        Settings::set('image_price_usd', (string)max(0, (float)input('image_price_usd', '0.05')));
        $budget = trim((string)input('default_monthly_budget', ''));
        Settings::set('default_monthly_budget', $budget === '' ? '' : (string)max(0, (float)$budget));

        flash('success', 'Đã lưu cài đặt hệ thống.');
        redirect('/settings');
    }

    public function testClaude(): void
    {
        Auth::requireAdmin();
        try {
            $res = ClaudeService::fromSettings()->complete('Bạn là trợ lý ngắn gọn.', 'Trả lời đúng một câu: "Kết nối thành công".', 2000);
            json_response(['ok' => true, 'message' => trim($res['text']) . ' (model ' . $res['model'] . ')']);
        } catch (\Throwable $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function testGoogle(): void
    {
        Auth::requireAdmin();
        $sheetId = trim((string)input('sheet_id', ''));
        try {
            $gs = GoogleSheetsService::fromSettings();
            if ($sheetId === '') {
                json_response(['ok' => true, 'message' => 'Đã đọc được Service Account: ' . GoogleSheetsService::serviceEmail()]);
            }
            $meta = $gs->meta($sheetId);
            json_response(['ok' => true, 'message' => 'Truy cập được sheet: ' . ($meta['properties']['title'] ?? $sheetId)]);
        } catch (\Throwable $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function usage(): void
    {
        Auth::requireRole('leader');
        $month = preg_match('~^\d{4}-\d{2}$~', (string)input('month', '')) ? (string)input('month') : date('Y-m');
        $from = $month . '-01 00:00:00';
        $to = date('Y-m-d H:i:s', strtotime($from . ' +1 month'));
        $byUser = db()->fetchAll(
            'SELECT u.name, l.user_id, SUM(l.cost_usd) cost, SUM(l.input_tokens) tin, SUM(l.output_tokens) tout, SUM(l.images) imgs,
                    SUM(l.task = \'write\') writes
             FROM usage_logs l LEFT JOIN users u ON u.id = l.user_id
             WHERE l.created_at >= ? AND l.created_at < ? GROUP BY l.user_id, u.name ORDER BY cost DESC',
            [$from, $to]
        );
        $byProject = db()->fetchAll(
            'SELECT p.name, l.project_id, SUM(l.cost_usd) cost, SUM(l.task = \'write\') writes, SUM(l.images) imgs
             FROM usage_logs l LEFT JOIN projects p ON p.id = l.project_id
             WHERE l.created_at >= ? AND l.created_at < ? GROUP BY l.project_id, p.name ORDER BY cost DESC',
            [$from, $to]
        );
        $total = (float)db()->value('SELECT COALESCE(SUM(cost_usd),0) FROM usage_logs WHERE created_at >= ? AND created_at < ?', [$from, $to]);
        view('usage', ['pageTitle' => 'Chi phí AI', 'month' => $month, 'byUser' => $byUser, 'byProject' => $byProject, 'total' => $total]);
    }

    public function jobs(): void
    {
        Auth::requireAdmin();
        $status = (string)input('status', '');
        $params = [];
        $where = '1=1';
        if (in_array($status, ['pending', 'running', 'done', 'failed'], true)) {
            $where = 'j.status = ?';
            $params[] = $status;
        }
        $jobs = db()->fetchAll(
            "SELECT j.*, p.name AS project_name, a.keyword, u.name AS user_name FROM jobs j
             LEFT JOIN projects p ON p.id = j.project_id LEFT JOIN articles a ON a.id = j.article_id LEFT JOIN users u ON u.id = j.user_id
             WHERE $where ORDER BY j.id DESC LIMIT 200",
            $params
        );
        $counts = [];
        foreach (db()->fetchAll('SELECT status, COUNT(*) c FROM jobs GROUP BY status') as $r) {
            $counts[$r['status']] = (int)$r['c'];
        }
        view('jobs', ['pageTitle' => 'Hàng đợi xử lý', 'jobs' => $jobs, 'counts' => $counts, 'status' => $status]);
    }

    public function retryJob(int $id): void
    {
        Auth::requireAdmin();
        $job = db()->fetch('SELECT * FROM jobs WHERE id = ?', [$id]) ?? abort(404);
        db()->update('jobs', ['status' => 'pending', 'attempts' => 0, 'last_error' => null, 'available_at' => now(), 'finished_at' => null, 'locked_by' => null], 'id = ?', [$id]);
        if ($job['article_id'] && \App\Jobs::isArticleTask($job['type'])) {
            Article::setAiState((int)$job['article_id'], 'queued', $job['type']);
        }
        flash('success', 'Đã đưa job #' . $id . ' vào hàng đợi lại.');
        back();
    }
}
