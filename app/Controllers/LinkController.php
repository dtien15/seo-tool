<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Project;
use App\Queue;

class LinkController
{
    public function index(int $id): void
    {
        Auth::requireLogin();
        $project = Project::findOrFail($id);
        $params = [$id];
        $where = 'project_id = ?';
        $q = (string)input('q', '');
        if ($q !== '') {
            $where .= ' AND (url LIKE ? OR title LIKE ? OR keywords LIKE ?)';
            array_push($params, "%$q%", "%$q%", "%$q%");
        }
        $total = (int)db()->value("SELECT COUNT(*) FROM internal_links WHERE $where", $params);
        $pg = paginate($total, 100, (int)input('page', 1));
        $links = db()->fetchAll("SELECT * FROM internal_links WHERE $where ORDER BY id DESC LIMIT {$pg['per_page']} OFFSET {$pg['offset']}", $params);
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
            'tab' => 'links',
        ]);
    }

    /** Thêm link thủ công, mỗi dòng: URL | tiêu đề | từ khóa */
    public function store(int $id): void
    {
        Auth::requireLogin();
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
        $user = Auth::requireLogin();
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
        Auth::requireLogin();
        Project::findOrFail($id);
        db()->query('DELETE FROM internal_links WHERE project_id = ?', [$id]);
        flash('success', 'Đã xóa toàn bộ link nội bộ của dự án.');
        redirect("/projects/$id/links");
    }

    public function update(int $linkId): void
    {
        Auth::requireLogin();
        $link = db()->fetch('SELECT * FROM internal_links WHERE id = ?', [$linkId]) ?? abort(404);
        Project::findOrFail((int)$link['project_id']);
        db()->update('internal_links', [
            'title' => mb_substr((string)input('title', ''), 0, 500) ?: null,
            'keywords' => mb_substr((string)input('keywords', ''), 0, 500) ?: null,
            'title_fetched' => 1,
        ], 'id = ?', [$linkId]);
        json_response(['ok' => true]);
    }

    public function destroy(int $linkId): void
    {
        Auth::requireLogin();
        $link = db()->fetch('SELECT * FROM internal_links WHERE id = ?', [$linkId]) ?? abort(404);
        Project::findOrFail((int)$link['project_id']);
        db()->query('DELETE FROM internal_links WHERE id = ?', [$linkId]);
        if (wants_json()) {
            json_response(['ok' => true]);
        }
        back();
    }
}
