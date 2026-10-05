<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Crypto;
use App\Models\Project;
use App\Services\WordPressService;

class ProjectController
{
    private const TEXT_FIELDS = ['name', 'domain', 'niche', 'brand_voice', 'target_audience', 'main_keywords', 'content_rules', 'wp_url', 'wp_username', 'sitemap_url'];

    public function index(): void
    {
        $user = Auth::requireLogin();
        view('projects/index', ['pageTitle' => 'Dự án', 'projects' => Project::forUser($user)]);
    }

    public function create(): void
    {
        Auth::requireLogin();
        view('projects/form', ['pageTitle' => 'Tạo dự án mới', 'project' => null]);
    }

    public function store(): void
    {
        $user = Auth::requireLogin();
        $data = $this->collect(null);
        $data['owner_id'] = $user['id'];
        $data['sheet_token'] = random_token(20);
        $id = db()->insert('projects', $data);
        flash('success', 'Đã tạo dự án. Bước tiếp theo: nhập link sitemap để lấy interlink, rồi thêm bài viết.');
        redirect('/projects/' . $id);
    }

    public function show(int $id): void
    {
        Auth::requireLogin();
        $project = Project::findOrFail($id);

        $where = ['a.project_id = ?'];
        $params = [$id];
        $status = (string)input('status', '');
        if ($status !== '' && isset(article_statuses()[$status])) {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }
        $assignee = input_int('assignee');
        if ($assignee) {
            $where[] = 'a.assigned_to = ?';
            $params[] = $assignee;
        }
        $q = (string)input('q', '');
        if ($q !== '') {
            $where[] = '(a.keyword LIKE ? OR a.title LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $whereSql = implode(' AND ', $where);
        $total = (int)db()->value("SELECT COUNT(*) FROM articles a WHERE $whereSql", $params);
        $pg = paginate($total, 50, (int)input('page', 1));
        $articles = db()->fetchAll(
            "SELECT a.*, u.name AS assignee_name FROM articles a LEFT JOIN users u ON u.id = a.assigned_to
             WHERE $whereSql ORDER BY a.updated_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
            $params
        );
        $counts = [];
        foreach (db()->fetchAll('SELECT status, COUNT(*) c FROM articles WHERE project_id = ? GROUP BY status', [$id]) as $r) {
            $counts[$r['status']] = (int)$r['c'];
        }

        view('projects/show', [
            'pageTitle' => $project['name'],
            'project' => $project,
            'articles' => $articles,
            'counts' => $counts,
            'pg' => $pg,
            'assignees' => Project::assignableUsers($project),
            'linkCount' => (int)db()->value('SELECT COUNT(*) FROM internal_links WHERE project_id = ?', [$id]),
            'tab' => 'articles',
        ]);
    }

    public function edit(int $id): void
    {
        Auth::requireLogin();
        $project = Project::findOrFail($id);
        $allUsers = Auth::isAdmin() ? db()->fetchAll('SELECT id, name, email FROM users WHERE is_active = 1 ORDER BY name') : [];
        view('projects/form', [
            'pageTitle' => 'Cài đặt dự án',
            'project' => $project,
            'members' => Project::members($id),
            'allUsers' => $allUsers,
            'tab' => 'settings',
        ]);
    }

    public function update(int $id): void
    {
        Auth::requireLogin();
        $project = Project::findOrFail($id);
        db()->update('projects', $this->collect($project), 'id = ?', [$id]);
        flash('success', 'Đã lưu cài đặt dự án.');
        redirect('/projects/' . $id . '/settings');
    }

    public function destroy(int $id): void
    {
        $user = Auth::requireLogin();
        $project = Project::findOrFail($id);
        if (!Auth::isAdmin() && (int)$project['owner_id'] !== (int)$user['id']) {
            abort(403, 'Chỉ chủ dự án hoặc admin được xóa dự án.');
        }
        if ((string)input('confirm_name') !== $project['name']) {
            throw new \RuntimeException('Tên dự án nhập để xác nhận không khớp.');
        }
        $articleIds = db()->fetchAll('SELECT id FROM articles WHERE project_id = ?', [$id]);
        foreach ($articleIds as $a) {
            \App\Models\Article::delete((int)$a['id']);
        }
        db()->query('DELETE FROM internal_links WHERE project_id = ?', [$id]);
        db()->query('DELETE FROM project_members WHERE project_id = ?', [$id]);
        db()->query("DELETE FROM jobs WHERE project_id = ? AND status IN ('pending','failed')", [$id]);
        db()->query('DELETE FROM projects WHERE id = ?', [$id]);
        flash('success', 'Đã xóa dự án "' . $project['name'] . '".');
        redirect('/projects');
    }

    /** Admin chia sẻ dự án cho SEOer khác / đổi chủ dự án. */
    public function members(int $id): void
    {
        Auth::requireAdmin();
        $project = Project::findOrFail($id);
        $ids = array_map('intval', (array)($_POST['members'] ?? []));
        db()->query('DELETE FROM project_members WHERE project_id = ?', [$id]);
        foreach (array_unique($ids) as $uid) {
            if ($uid > 0 && $uid !== (int)$project['owner_id']) {
                db()->insert('project_members', ['project_id' => $id, 'user_id' => $uid]);
            }
        }
        $owner = input_int('owner_id');
        if ($owner && db()->value('SELECT 1 FROM users WHERE id = ?', [$owner])) {
            db()->update('projects', ['owner_id' => $owner], 'id = ?', [$id]);
        }
        flash('success', 'Đã cập nhật thành viên dự án.');
        redirect('/projects/' . $id . '/settings');
    }

    public function wpTest(int $id): void
    {
        Auth::requireLogin();
        $project = Project::findOrFail($id);
        $url = (string)input('wp_url', $project['wp_url']);
        $user = (string)input('wp_username', $project['wp_username']);
        $pass = (string)($_POST['wp_app_password'] ?? '');
        if ($pass === '') {
            $pass = (string)Crypto::decrypt($project['wp_app_password_enc']);
        }
        if ($url === '' || $user === '' || $pass === '') {
            json_response(['ok' => false, 'error' => 'Nhập đủ URL, username và Application Password.']);
        }
        try {
            $me = (new WordPressService($url, $user, $pass))->me();
            $roles = implode(', ', $me['roles'] ?? []);
            json_response(['ok' => true, 'message' => 'Kết nối thành công: ' . ($me['name'] ?? $user) . ($roles ? " ($roles)" : '')]);
        } catch (\Throwable $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    public function wpCategories(int $id): void
    {
        Auth::requireLogin();
        $project = Project::findOrFail($id);
        try {
            json_response(['ok' => true, 'categories' => Project::wordpress($project)->categories()]);
        } catch (\Throwable $e) {
            json_response(['ok' => false, 'error' => $e->getMessage()]);
        }
    }

    private function collect(?array $existing): array
    {
        $data = [];
        foreach (self::TEXT_FIELDS as $f) {
            $v = (string)input($f, '');
            $data[$f] = $v === '' ? null : $v;
        }
        if (!$data['name']) {
            throw new \RuntimeException('Vui lòng nhập tên dự án.');
        }
        foreach (['wp_url', 'sitemap_url'] as $f) {
            if ($data[$f] && !filter_var($data[$f], FILTER_VALIDATE_URL)) {
                throw new \RuntimeException('Link không hợp lệ: ' . $data[$f]);
            }
        }
        if ($data['wp_url']) {
            $data['wp_url'] = rtrim($data['wp_url'], '/');
        }
        $data['word_count'] = max(300, min(6000, (int)input('word_count', 1500)));
        $data['images_per_article'] = max(0, min(6, (int)input('images_per_article', 2)));
        $data['wp_default_status'] = in_array(input('wp_default_status'), ['draft', 'publish', 'pending'], true) ? input('wp_default_status') : 'draft';
        $data['wp_default_category_id'] = input_int('wp_default_category_id') ?: null;
        $data['auto_publish'] = input('auto_publish') ? 1 : 0;

        $pass = (string)($_POST['wp_app_password'] ?? '');
        if ($pass !== '') {
            $data['wp_app_password_enc'] = Crypto::encrypt(trim($pass));
        } elseif (input('wp_clear_password')) {
            $data['wp_app_password_enc'] = null;
        }
        return $data;
    }
}
