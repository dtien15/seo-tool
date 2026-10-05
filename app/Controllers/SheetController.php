<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Project;
use App\Services\GoogleSheetsService;
use App\Services\SheetSync;

class SheetController
{
    public function show(int $id): void
    {
        Auth::requireLogin();
        $project = Project::findOrFail($id);
        view('projects/sheet', [
            'pageTitle' => 'Google Sheet – ' . $project['name'],
            'project' => $project,
            'serviceEmail' => GoogleSheetsService::serviceEmail(),
            'script' => SheetSync::appsScript($project),
            'webhookUrl' => SheetSync::webhookUrl(),
            'tab' => 'sheet',
        ]);
    }

    public function save(int $id): void
    {
        Auth::requireLogin();
        Project::findOrFail($id);
        $raw = (string)input('gsheet_id', '');
        // Cho phép dán cả link Google Sheet
        if (preg_match('~/spreadsheets/d/([a-zA-Z0-9_-]+)~', $raw, $m)) {
            $raw = $m[1];
        }
        $tab = trim((string)input('gsheet_tab', '')) ?: 'Bai viet';
        $data = ['gsheet_id' => $raw !== '' ? $raw : null, 'gsheet_tab' => mb_substr($tab, 0, 100)];
        if (input('regenerate_token')) {
            $data['sheet_token'] = random_token(20);
        }
        db()->update('projects', $data, 'id = ?', [$id]);
        flash('success', $raw ? 'Đã lưu. Bấm "Khởi tạo & đồng bộ" để tạo cột và đẩy dữ liệu lên sheet.' : 'Đã ngắt kết nối Google Sheet.');
        redirect("/projects/$id/sheet");
    }

    public function init(int $id): void
    {
        Auth::requireLogin();
        $project = Project::findOrFail($id);
        if (!Project::hasSheet($project)) {
            throw new \RuntimeException('Chưa nhập Google Sheet.');
        }
        SheetSync::init($project);
        flash('success', 'Đã khởi tạo sheet và đồng bộ toàn bộ bài viết.');
        redirect("/projects/$id/sheet");
    }

    public function sync(int $id): void
    {
        Auth::requireLogin();
        $project = Project::findOrFail($id);
        if (!Project::hasSheet($project)) {
            throw new \RuntimeException('Chưa nhập Google Sheet.');
        }
        $changed = SheetSync::pull($project);
        $total = SheetSync::pushAll(Project::find($id));
        flash('success', "Đồng bộ xong: $changed thay đổi từ sheet, $total bài đã cập nhật lên sheet.");
        back();
    }

    /** Nhận thay đổi từ Apps Script trên Google Sheet. */
    public function webhook(): void
    {
        $body = json_decode((string)file_get_contents('php://input'), true);
        if (!is_array($body) || empty($body['token'])) {
            json_response(['ok' => false, 'error' => 'Thiếu token'], 400);
        }
        $project = db()->fetch('SELECT * FROM projects WHERE sheet_token = ?', [(string)$body['token']]);
        if (!$project) {
            json_response(['ok' => false, 'error' => 'Token không hợp lệ (kiểm tra lại mã Apps Script)'], 403);
        }
        try {
            $result = SheetSync::applyRow($project, (int)($body['row'] ?? 0), [
                'id' => trim((string)($body['id'] ?? '')),
                'keyword' => trim((string)($body['keyword'] ?? '')),
                'title' => trim((string)($body['title'] ?? '')),
                'status' => trim((string)($body['status'] ?? '')),
                'updated_at' => trim((string)($body['updated_at'] ?? '')),
            ]);
        } catch (\Throwable $e) {
            log_error('Webhook: ' . $e->getMessage());
            json_response(['ok' => false, 'error' => 'Lỗi máy chủ'], 500);
        }
        if (!empty($result['error'])) {
            json_response(['ok' => false, 'error' => $result['error']]);
        }
        json_response(['ok' => true, 'id' => $result['id'], 'action' => $result['action']]);
    }
}
