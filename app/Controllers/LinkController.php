<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Project;
use App\Queue;

class LinkController
{
    public const PAGE_TYPES = ['product' => 'Sản phẩm / dịch vụ', 'blog' => 'Bài blog', 'category' => 'Danh mục', 'page' => 'Trang khác'];
    public const AUDIT_ACTIONS = ['keep' => ['Giữ nguyên', 'success'], 'optimize' => ['Cần tối ưu', 'warning'], 'merge' => ['Gộp bài', 'info'], 'delete' => ['Xóa / chuyển hướng', 'danger']];

    public function index(int $id): void
    {
        Auth::requireRole('seo', 'leader');
        $project = Project::findOrFail($id);
        $params = [$id];
        $where = 'project_id = ?';
        $q = (string)input('q', '');
        if ($q !== '') {
            $where .= ' AND (url LIKE ? OR title LIKE ? OR keywords LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        $action = (string)input('audit', '');
        if ($action === '__none') {
            $where .= ' AND audit_action IS NULL';
        } elseif (isset(self::AUDIT_ACTIONS[$action])) {
            $where .= ' AND audit_action = ?';
            $params[] = $action;
        }
        $type = (string)input('type', '');
        if (isset(self::PAGE_TYPES[$type])) {
            $where .= ' AND page_type = ?';
            $params[] = $type;
        }
        $total = (int)db()->value("SELECT COUNT(*) FROM internal_links WHERE $where", $params);
        $pg = paginate($total, 100, (int)input('page', 1));
        $links = db()->fetchAll(
            "SELECT l.*, (SELECT a.id FROM articles a WHERE a.project_id = l.project_id AND a.wp_url = l.url LIMIT 1) AS article_id
             FROM internal_links l WHERE $where ORDER BY id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}",
            $params
        );
        $auditCounts = [];
        foreach (db()->fetchAll("SELECT COALESCE(audit_action, '__none') a, COUNT(*) c FROM internal_links WHERE project_id = ? GROUP BY a", [$id]) as $r) {
            $auditCounts[$r['a']] = (int)$r['c'];
        }
        $pending = db()->fetchAll(
            "SELECT type, status, last_error, created_at FROM jobs WHERE project_id = ? AND type IN ('import_sitemap','import_wordpress','fetch_titles')
             ORDER BY id DESC LIMIT 5",
            [$id]
        );
        view('projects/links', [
            'pageTitle' => 'Interlink – ' . $project['name'],
            'project' => $project,
            'links' => $links,
            'pg' => $pg,
            'jobs' => $pending,
            'untitled' => (int)db()->value('SELECT COUNT(*) FROM internal_links WHERE project_id = ? AND title_fetched = 0', [$id]),
            'auditCounts' => $auditCounts,
            'tab' => 'links',
        ]);
    }

    /** Thêm link thủ công, mỗi dòng: URL | tiêu đề | từ khóa */
    public function store(int $id): void
    {
        Auth::requireRole('seo', 'leader');
        Project::findOrFail($id);
        $added = 0;
        foreach (preg_split('~\R~', (string)input('lines', '')) as $line) {
            $parts = array_map('trim', explode('|', $line));
            $url = $parts[0] ?? '';
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                continue;
            }
            $added += db()->query(
                'INSERT INTO internal_links (project_id, url, url_hash, title, keywords, source, title_fetched) VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE title = COALESCE(VALUES(title), title), keywords = COALESCE(VALUES(keywords), keywords)',
                [$id, mb_substr($url, 0, 1000), sha1($url), ($parts[1] ?? '') ?: null, ($parts[2] ?? '') ?: null, 'manual', empty($parts[1]) ? 0 : 1]
            )->rowCount() > 0 ? 1 : 0;
        }
        flash('success', "Đã thêm/cập nhật $added link.");
        redirect("/projects/$id/links");
    }

    public function import(int $id): void
    {
        $user = Auth::requireRole('seo', 'leader');
        $project = Project::findOrFail($id);
        $source = input('source') === 'wordpress' ? 'wordpress' : 'sitemap';
        if ($source === 'sitemap') {
            $url = (string)input('sitemap_url', $project['sitemap_url']);
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new \RuntimeException('Link sitemap không hợp lệ.');
            }
            db()->update('projects', ['sitemap_url' => $url], 'id = ?', [$id]);
        } elseif (empty($project['wp_url'])) {
            throw new \RuntimeException('Dự án chưa cấu hình WordPress.');
        }
        $type = 'import_' . $source;
        if (!Queue::projectHasPending($id, $type)) {
            Queue::push($type, [], ['project_id' => $id, 'user_id' => $user['id']]);
        }
        flash('info', 'Đã đưa vào hàng đợi. Danh sách link sẽ cập nhật sau khoảng 1-2 phút.');
        redirect("/projects/$id/links");
    }

    public function clear(int $id): void
    {
        Auth::requireRole('seo', 'leader');
        Project::findOrFail($id);
        db()->query('DELETE FROM internal_links WHERE project_id = ?', [$id]);
        flash('success', 'Đã xóa toàn bộ link nội bộ của dự án.');
        redirect("/projects/$id/links");
    }

    public function update(int $linkId): void
    {
        Auth::requireRole('seo', 'leader');
        $link = db()->fetch('SELECT * FROM internal_links WHERE id = ?', [$linkId]) ?? abort(404);
        Project::findOrFail((int)$link['project_id']);
        $data = [];
        foreach (['title' => 500, 'keywords' => 500, 'cluster' => 190, 'audit_note' => 2000] as $f => $max) {
            if (array_key_exists($f, $_POST)) {
                $data[$f] = mb_substr(trim((string)$_POST[$f]), 0, $max) ?: null;
            }
        }
        if (array_key_exists('page_type', $_POST)) {
            $data['page_type'] = isset(self::PAGE_TYPES[$_POST['page_type']]) ? $_POST['page_type'] : null;
        }
        if (array_key_exists('audit_action', $_POST)) {
            $data['audit_action'] = isset(self::AUDIT_ACTIONS[$_POST['audit_action']]) ? $_POST['audit_action'] : null;
        }
        if (isset($data['title'])) {
            $data['title_fetched'] = 1;
        }
        if ($data) {
            db()->update('internal_links', $data, 'id = ?', [$linkId]);
        }
        json_response(['ok' => true]);
    }

    /** Tạo việc tối ưu lại cho một trang đang có trên website. */
    public function optimize(int $linkId): void
    {
        $user = Auth::requireRole('seo', 'leader');
        $link = db()->fetch('SELECT * FROM internal_links WHERE id = ?', [$linkId]) ?? abort(404);
        $project = Project::findOrFail((int)$link['project_id']);
        $existing = db()->value("SELECT id FROM articles WHERE project_id = ? AND wp_url = ? AND status NOT IN ('published','wp_draft') LIMIT 1", [$project['id'], $link['url']]);
        if ($existing) {
            redirect('/articles/' . $existing);
        }
        $keyword = trim(explode(',', (string)$link['keywords'])[0]) ?: (string)$link['title'];
        $id = \App\Models\Article::create($project, [
            'keyword' => $keyword ?: $link['url'],
            'title' => $link['title'],
            'type' => 'optimize',
            'wp_url' => $link['url'],
            'cluster' => $link['cluster'],
            'notes' => $link['audit_note'] ? 'Đề xuất tối ưu: ' . $link['audit_note'] : null,
            'assigned_to' => $user['id'],
        ]);
        \App\Models\Article::log($id, 'created', 'Tối ưu lại trang hiện có: ' . $link['url']);
        db()->update('internal_links', ['audit_action' => 'optimize'], 'id = ?', [$linkId]);
        flash('success', 'Đã tạo việc tối ưu lại. Bấm "Lấy nội dung từ WordPress" để tải bài hiện tại về.');
        redirect('/articles/' . $id);
    }

    public function destroy(int $linkId): void
    {
        Auth::requireRole('seo', 'leader');
        $link = db()->fetch('SELECT * FROM internal_links WHERE id = ?', [$linkId]) ?? abort(404);
        Project::findOrFail((int)$link['project_id']);
        Auth::requireRole('seo', 'leader');
        db()->query('DELETE FROM internal_links WHERE id = ?', [$linkId]);
        if (wants_json()) {
            json_response(['ok' => true]);
        }
        back();
    }
}
