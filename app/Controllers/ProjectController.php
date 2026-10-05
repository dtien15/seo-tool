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
        Auth::requireRole('seo', 'leader');
        view('projects/form', ['pageTitle' => 'Tạo dự án mới', 'project' => null]);
    }

    public function store(): void
    {
        $user = Auth::requireRole('seo', 'leader');
        $data = $this->collect(null);
        $data['owner_id'] = $user['id'];
        $data['sheet_token'] = random_token(20);
        $id = db()->insert('projects', $data);
        flash('success', 'Đã tạo dự án. Làm theo các bước ở trang Tổng quan để triển khai quy trình.');
        redirect('/projects/' . $id . '/overview');
    }

    public function show(int $id): void
    {
        Auth::requireLogin();
        $project = Project::findOrFail($id);

        [$scope, $scopeParams] = Project::articleScope();
        $where = ['a.project_id = ?', $scope];
        $params = array_merge([$id], $scopeParams);
        if (input('review') === 'due') {
            $where[] = \App\Workflow::dueForReview($id, (int)$project['reoptimize_days']);
        }
        $cluster = (string)input('cluster', '');
        if ($cluster !== '') {
            $where[] = 'a.cluster = ?';
            $params[] = $cluster;
        }
        $status = (string)input('status', '');
        if ($status !== '' && isset(article_statuses()[$status])) {
            $where[] = 'a.status = ?';
            $params[] = $status;
        }
        $assignee = input_int('assignee');
        if ($assignee) {
            $where[] = '(a.assigned_to = ? OR a.writer_id = ? OR a.designer_id = ?)';
            array_push($params, $assignee, $assignee, $assignee);
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
            "SELECT a.*, u.name AS assignee_name, w.name AS writer_name, d.name AS designer_name FROM articles a
             LEFT JOIN users u ON u.id = a.assigned_to LEFT JOIN users w ON w.id = a.writer_id LEFT JOIN users d ON d.id = a.designer_id
             WHERE $whereSql ORDER BY a.planned_date IS NULL, a.planned_date, a.updated_at DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
            $params
        );
        $counts = [];
        foreach (db()->fetchAll("SELECT status, COUNT(*) c FROM articles a WHERE project_id = ? AND $scope GROUP BY status", array_merge([$id], $scopeParams)) as $r) {
            $counts[$r['status']] = (int)$r['c'];
        }

        view('projects/show', [
            'pageTitle' => $project['name'],
            'project' => $project,
            'articles' => $articles,
            'counts' => $counts,
            'pg' => $pg,
            'assignees' => Project::assignableUsers($project),
            'writers' => Project::usersByRole('content'),
            'designers' => Project::usersByRole('design'),
            'clusters' => array_column(db()->fetchAll("SELECT DISTINCT cluster FROM articles WHERE project_id = ? AND cluster IS NOT NULL AND cluster <> '' ORDER BY cluster", [$id]), 'cluster'),
            'due' => (int)db()->value('SELECT COUNT(*) FROM articles a WHERE ' . \App\Workflow::dueForReview($id, (int)$project['reoptimize_days'])),
            'linkCount' => (int)db()->value('SELECT COUNT(*) FROM internal_links WHERE project_id = ?', [$id]),
            'tab' => 'articles',
        ]);
    }

    public function edit(int $id): void
    {
        Auth::requireRole('seo', 'leader');
        $project = Project::findOrFail($id);
        $allUsers = Auth::is('leader') ? db()->fetchAll('SELECT id, name, email, role FROM users WHERE is_active = 1 ORDER BY name') : [];
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
        Auth::requireRole('seo', 'leader');
        $project = Project::findOrFail($id);
        db()->update('projects', $this->collect($project), 'id = ?', [$id]);
        flash('success', 'Đã lưu cài đặt dự án.');
        redirect('/projects/' . $id . '/settings');
    }

    public function destroy(int $id): void
    {
        $user = Auth::requireLogin();
        $project = Project::findOrFail($id);
        if (!Auth::is('leader') && (int)$project['owner_id'] !== (int)$user['id']) {
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
        foreach (['keywords', 'kpis', 'audit_items', 'project_research'] as $t) {
            db()->query("DELETE FROM $t WHERE project_id = ?", [$id]);
        }
        db()->query('DELETE FROM competitor_keywords WHERE competitor_id IN (SELECT id FROM competitors WHERE project_id = ?)', [$id]);
        db()->query('DELETE FROM competitor_metrics WHERE competitor_id IN (SELECT id FROM competitors WHERE project_id = ?)', [$id]);
        db()->query('DELETE FROM competitors WHERE project_id = ?', [$id]);
        db()->query("DELETE FROM jobs WHERE project_id = ? AND status IN ('pending','failed')", [$id]);
        db()->query('DELETE FROM projects WHERE id = ?', [$id]);
        flash('success', 'Đã xóa dự án "' . $project['name'] . '".');
        redirect('/projects');
    }

    /** Admin chia sẻ dự án cho SEOer khác / đổi chủ dự án. */
    public function members(int $id): void
    {
        Auth::requireRole('leader');
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
        $data['reoptimize_days'] = max(7, min(365, (int)input('reoptimize_days', 30)));

        $pass = (string)($_POST['wp_app_password'] ?? '');
        if ($pass !== '') {
            $data['wp_app_password_enc'] = Crypto::encrypt(trim($pass));
        } elseif (input('wp_clear_password')) {
            $data['wp_app_password_enc'] = null;
        }
        return $data;
    }
}
