<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Article;
use App\Models\Project;
use App\Queue;
use App\Services\ContentWriter;
use App\Settings;
use App\Workflow;

class ArticleController
{
    private const TASK_LABELS = [
        'outline' => 'tạo outline',
        'write' => 'AI viết bài',
        'images' => 'AI tạo hình',
        'publish' => 'đăng WordPress',
    ];

    private const UPLOAD_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];

    public function create(int $projectId): void
    {
        Auth::requireRole('seo', 'leader');
        $project = Project::findOrFail($projectId);
        view('articles/create', [
            'pageTitle' => 'Thêm bài vào kế hoạch',
            'project' => $project,
            'assignees' => Project::assignableUsers($project),
            'writers' => Project::usersByRole('content'),
            'designers' => Project::usersByRole('design'),
            'tab' => 'articles',
        ]);
    }

    public function store(int $projectId): void
    {
        $user = Auth::requireRole('seo', 'leader');
        $project = Project::findOrFail($projectId);
        $common = [
            'assigned_to' => input_int('assigned_to') ?: (int)$user['id'],
            'writer_id' => input_int('writer_id'),
            'designer_id' => input_int('designer_id'),
            'word_count' => input_int('word_count'),
            'cluster' => (string)input('cluster', '') ?: null,
            'planned_date' => preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)input('planned_date')) ? input('planned_date') : null,
            'type' => input('type') === 'optimize' ? 'optimize' : 'new',
        ];
        $ids = [];

        if (input('mode') === 'bulk') {
            // Mỗi dòng: từ khóa chính | từ khóa phụ | ghi chú
            foreach (preg_split('~\R~', (string)input('lines', '')) as $line) {
                $parts = array_map('trim', explode('|', $line, 3));
                if (($parts[0] ?? '') === '') {
                    continue;
                }
                $ids[] = Article::create($project, $common + [
                    'keyword' => $parts[0],
                    'secondary_keywords' => ($parts[1] ?? '') ?: null,
                    'notes' => ($parts[2] ?? '') ?: null,
                ]);
            }
        } else {
            $keyword = (string)input('keyword', '');
            if ($keyword === '') {
                throw new \RuntimeException('Vui lòng nhập từ khóa chính.');
            }
            $wpUrl = (string)input('wp_url', '');
            if ($common['type'] === 'optimize' && !filter_var($wpUrl, FILTER_VALIDATE_URL)) {
                throw new \RuntimeException('Bài tối ưu lại cần link bài viết hiện có.');
            }
            $ids[] = Article::create($project, $common + [
                'keyword' => $keyword,
                'secondary_keywords' => (string)input('secondary_keywords', '') ?: null,
                'notes' => (string)input('notes', '') ?: null,
                'title' => (string)input('title', '') ?: null,
                'wp_url' => $wpUrl ?: null,
            ]);
        }
        if (!$ids) {
            throw new \RuntimeException('Chưa có từ khóa nào hợp lệ.');
        }
        foreach ($ids as $id) {
            Article::log($id, 'created', 'Thêm vào kế hoạch content');
        }

        $queued = 0;
        if (input('then') === 'outline') {
            foreach ($ids as $id) {
                Queue::articleTask('outline', $id, (int)$user['id']);
                $queued++;
            }
        }
        if (Project::hasSheet($project)) {
            count($ids) === 1
                ? Article::syncToSheet($ids[0])
                : Queue::push('sheet_push_all', [], ['project_id' => $projectId]);
        }

        flash('success', 'Đã thêm ' . count($ids) . ' bài vào kế hoạch.' . ($queued ? " $queued bài đang được AI tạo outline." : ''));
        redirect(count($ids) === 1 ? '/articles/' . $ids[0] : '/projects/' . $projectId);
    }

    public function edit(int $id): void
    {
        $user = Auth::requireLogin();
        [$article, $project] = Article::findOrFail($id);
        view('articles/edit', [
            'pageTitle' => $article['title'] ?: $article['keyword'],
            'project' => $project,
            'article' => $article,
            'images' => Article::images($id),
            'logs' => Article::logs($id),
            'buttons' => Workflow::buttons($article, $user),
            'approvals' => [
                'outline' => Workflow::approvals($article, 'outline'),
                'content' => Workflow::approvals($article, 'content'),
                'image' => Workflow::approvals($article, 'image'),
            ],
            'assignees' => Project::assignableUsers($project),
            'writers' => Project::usersByRole('content'),
            'designers' => Project::usersByRole('design'),
            'perm' => self::permissions($article, $user),
            'hasAi' => \App\Services\AiText::ready(),
            'hasOpenAI' => Settings::has('openai_api_key'),
            'linkCount' => (int)db()->value('SELECT COUNT(*) FROM internal_links WHERE project_id = ?', [$project['id']]),
            'tab' => 'articles',
        ]);
    }

    /** Quyền sửa từng phần của bài theo vai trò. */
    public static function permissions(array $article, array $user): array
    {
        $role = $user['role'];
        $manager = in_array($role, ['admin', 'leader', 'seo'], true);
        return [
            'brief' => $manager,                                             // từ khóa, ghi chú, phân công
            'outline' => $manager,
            'content' => $manager || $role === 'content',
            'images' => $manager || $role === 'design',
            'force_status' => in_array($role, ['admin', 'leader'], true),
            'delete' => in_array($role, ['admin', 'leader', 'seo'], true),
            'publish' => Auth::is('seo', 'leader') && in_array($article['status'], ['ready', 'wp_draft', 'published'], true),
        ];
    }

    public function update(int $id): void
    {
        $user = Auth::requireLogin();
        [$article, $project] = Article::findOrFail($id);
        $perm = self::permissions($article, $user);
        if (in_array($article['ai_state'], ['queued', 'running'], true) && in_array($article['ai_task'], ['write', 'outline'], true)) {
            throw new \RuntimeException('AI đang xử lý bài này, vui lòng đợi xong rồi lưu.');
        }

        $data = [];
        if ($perm['brief']) {
            $keyword = (string)input('keyword', '');
            if ($keyword === '') {
                throw new \RuntimeException('Từ khóa chính không được để trống.');
            }
            $data += [
                'keyword' => mb_substr($keyword, 0, 255),
                'secondary_keywords' => (string)input('secondary_keywords', '') ?: null,
                'notes' => (string)input('notes', '') ?: null,
                'word_count' => input_int('word_count') ?: null,
                'cluster' => mb_substr((string)input('cluster', ''), 0, 190) ?: null,
                'planned_date' => preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)input('planned_date')) ? input('planned_date') : null,
                'assigned_to' => input_int('assigned_to') ?: null,
                'writer_id' => input_int('writer_id') ?: null,
                'designer_id' => input_int('designer_id') ?: null,
                'wp_category_id' => input_int('wp_category_id') ?: null,
            ];
        }
        if ($perm['outline']) {
            $data['outline'] = (string)input('outline', '') ?: null;
        }
        if ($perm['content']) {
            $data += [
                'title' => mb_substr((string)input('title', ''), 0, 500) ?: null,
                'slug' => slugify((string)input('slug', '')) ?: null,
                'meta_title' => mb_substr((string)input('meta_title', ''), 0, 255) ?: null,
                'meta_description' => mb_substr((string)input('meta_description', ''), 0, 500) ?: null,
                'content' => ContentWriter::sanitizeHtml((string)($_POST['content'] ?? '')) ?: null,
            ];
        }
        if ($perm['images']) {
            foreach ((array)($_POST['image_alt'] ?? []) as $imgId => $alt) {
                db()->update('article_images', ['alt_text' => mb_substr(trim((string)$alt), 0, 250)], 'id = ? AND article_id = ?', [(int)$imgId, $id]);
            }
        }
        if ($data) {
            db()->update('articles', $data, 'id = ?', [$id]);
        }
        foreach (['writer_id' => 'Content', 'designer_id' => 'Design', 'assigned_to' => 'SEO'] as $field => $label) {
            if (array_key_exists($field, $data) && (int)$data[$field] !== (int)$article[$field] && $data[$field]) {
                $name = db()->value('SELECT name FROM users WHERE id = ?', [$data[$field]]);
                Article::log($id, 'assign', "Giao $label: $name");
            }
        }

        $status = (string)input('status', $article['status']);
        if ($perm['force_status'] && $status !== $article['status'] && isset(article_statuses()[$status])) {
            Article::log($id, 'status', 'Đổi thủ công: ' . status_label($article['status']) . ' → ' . status_label($status));
            Article::setStatus($id, $status);
        } elseif (array_intersect_key($data, ['title' => 1, 'keyword' => 1, 'assigned_to' => 1])) {
            Article::syncToSheet($id);
        }

        if (input('then') === 'publish') {
            Queue::articleTask('publish', $id, Auth::id());
            flash('success', 'Đã lưu và đưa vào hàng đợi đăng WordPress.');
        } else {
            flash('success', 'Đã lưu bài viết.');
        }
        redirect('/articles/' . $id);
    }

    /** Các bước duyệt / gửi duyệt của quy trình. */
    public function workflow(int $id): void
    {
        Auth::requireLogin();
        Article::findOrFail($id);
        $message = Workflow::apply((string)input('action'), $id, (string)input('note', ''), (string)input('side', '') ?: null);
        flash('success', $message);
        redirect('/articles/' . $id);
    }

    /** Trạng thái để trang tự cập nhật khi AI đang chạy. */
    public function state(int $id): void
    {
        Auth::requireLogin();
        [$article] = Article::findOrFail($id);
        json_response([
            'ok' => true,
            'ai_state' => $article['ai_state'],
            'ai_task' => $article['ai_task'],
            'ai_error' => $article['ai_error'],
            'status' => $article['status'],
            'updated_at' => $article['updated_at'],
        ]);
    }

    public function task(int $id): void
    {
        $user = Auth::requireLogin();
        Article::findOrFail($id);
        $task = (string)input('task');
        if (!isset(self::TASK_LABELS[$task])) {
            throw new \RuntimeException('Tác vụ không hợp lệ.');
        }
        Queue::articleTask($task, $id, (int)$user['id']);
        flash('info', 'Đã đưa vào hàng đợi ' . self::TASK_LABELS[$task] . '. Trang sẽ tự cập nhật khi xong.');
        redirect('/articles/' . $id);
    }

    /** Designer upload hình: thay hình ở một vị trí có sẵn, hoặc thêm hình mới. */
    public function uploadImage(int $id): void
    {
        $user = Auth::requireLogin();
        [$article, $project] = Article::findOrFail($id);
        if (!self::permissions($article, $user)['images']) {
            abort(403, 'Bạn không có quyền sửa hình của bài này.');
        }
        $file = $_FILES['image'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Chưa chọn file hình hoặc upload lỗi (tối đa ' . ini_get('upload_max_filesize') . ').');
        }
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new \RuntimeException('Hình tối đa 10MB.');
        }
        $info = @getimagesize($file['tmp_name']);
        $ext = self::UPLOAD_TYPES[$info['mime'] ?? ''] ?? null;
        if (!$ext) {
            throw new \RuntimeException('Chỉ nhận hình JPG, PNG, WEBP, GIF.');
        }

        $slotId = input_int('image_id');
        $slot = $slotId ? db()->fetch('SELECT * FROM article_images WHERE id = ? AND article_id = ?', [$slotId, $id]) : null;
        if (!$slot) {
            $hasFeatured = (bool)db()->value('SELECT 1 FROM article_images WHERE article_id = ? AND position = 0', [$id]);
            $position = $hasFeatured ? (int)db()->value('SELECT COALESCE(MAX(position),0) + 1 FROM article_images WHERE article_id = ?', [$id]) : 0;
            $slotId = db()->insert('article_images', ['article_id' => $id, 'position' => $position, 'alt_text' => mb_substr((string)input('alt', '') ?: $article['keyword'], 0, 250)]);
            $slot = db()->fetch('SELECT * FROM article_images WHERE id = ?', [$slotId]);
        }

        $dir = 'uploads/' . $project['id'] . '/' . date('Y-m');
        if (!is_dir(BASE_PATH . '/' . $dir)) {
            mkdir(BASE_PATH . '/' . $dir, 0775, true);
        }
        $base = slugify(pathinfo((string)$file['name'], PATHINFO_FILENAME)) ?: 'hinh';
        $relative = $dir . '/' . $id . '-' . $slot['position'] . '-' . mb_substr($base, 0, 60) . '-' . substr(random_token(3), 0, 5) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], BASE_PATH . '/' . $relative)) {
            throw new \RuntimeException('Không lưu được file (kiểm tra quyền ghi thư mục uploads).');
        }

        $content = \App\Jobs::placeImage((string)$article['content'], $slot, $relative);
        if ($slot['file_path'] && is_file(BASE_PATH . '/' . $slot['file_path'])) {
            @unlink(BASE_PATH . '/' . $slot['file_path']);
        }
        db()->update('article_images', ['file_path' => $relative, 'source' => 'upload', 'uploaded_by' => $user['id'], 'wp_media_id' => null, 'wp_url' => null], 'id = ?', [$slot['id']]);
        db()->update('articles', ['content' => $content], 'id = ?', [$id]);
        Article::log($id, 'upload_image', ((int)$slot['position'] === 0 ? 'Ảnh đại diện' : 'Hình ' . $slot['position']) . ': ' . $file['name']);
        flash('success', 'Đã upload hình.');
        redirect('/articles/' . $id . '#images');
    }

    public function deleteImage(int $id, int $imageId): void
    {
        $user = Auth::requireLogin();
        [$article] = Article::findOrFail($id);
        if (!self::permissions($article, $user)['images']) {
            abort(403);
        }
        $img = db()->fetch('SELECT * FROM article_images WHERE id = ? AND article_id = ?', [$imageId, $id]) ?? abort(404);
        $content = (string)$article['content'];
        foreach (array_filter([$img['file_path'] ? absolute_url($img['file_path']) : null, $img['wp_url']]) as $src) {
            $content = preg_replace('~<figure[^>]*>\s*<img[^>]+src="' . preg_quote(e($src), '~') . '"[^>]*>\s*</figure>~', '<p>[IMAGE_' . $img['position'] . ']</p>', $content) ?? $content;
        }
        if ($img['file_path'] && is_file(BASE_PATH . '/' . $img['file_path'])) {
            @unlink(BASE_PATH . '/' . $img['file_path']);
        }
        db()->update('article_images', ['file_path' => null, 'wp_media_id' => null, 'wp_url' => null], 'id = ?', [$imageId]);
        db()->update('articles', ['content' => $content], 'id = ?', [$id]);
        flash('success', 'Đã gỡ hình (vị trí hình vẫn giữ để upload hình khác).');
        redirect('/articles/' . $id . '#images');
    }

    public function regenerateImage(int $id, int $imageId): void
    {
        $user = Auth::requireLogin();
        Article::findOrFail($id);
        $prompt = trim((string)input('prompt', ''));
        Queue::articleTask('image', $id, (int)$user['id'], ['image_id' => $imageId, 'prompt' => $prompt ?: null]);
        flash('info', 'AI đang tạo hình...');
        redirect('/articles/' . $id . '#images');
    }

    /** Bài tối ưu lại: lấy nội dung hiện tại của bài trên WordPress về để sửa. */
    public function wpImport(int $id): void
    {
        $user = Auth::requireLogin();
        [$article, $project] = Article::findOrFail($id);
        if (!self::permissions($article, $user)['brief']) {
            abort(403);
        }
        $url = (string)$article['wp_url'];
        $slug = basename(trim((string)parse_url($url, PHP_URL_PATH), '/'));
        if ($slug === '') {
            throw new \RuntimeException('Bài chưa có link bài viết trên website.');
        }
        $wp = Project::wordpress($project);
        $post = null;
        foreach (['posts', 'pages'] as $type) {
            $found = $wp->request('GET', $type . '?slug=' . rawurlencode(urldecode($slug)) . '&context=edit&_fields=id,link,title,content,excerpt');
            if ($found) {
                $post = $found[0];
                break;
            }
        }
        if (!$post) {
            throw new \RuntimeException('Không tìm thấy bài có slug "' . $slug . '" trên WordPress (chỉ hỗ trợ bài viết/trang).');
        }
        db()->update('articles', [
            'wp_post_id' => (int)$post['id'],
            'wp_url' => (string)$post['link'],
            'title' => html_entity_decode((string)($post['title']['raw'] ?? $post['title']['rendered'] ?? ''), ENT_QUOTES, 'UTF-8') ?: $article['title'],
            'slug' => $slug,
            'content' => (string)($post['content']['raw'] ?? $post['content']['rendered'] ?? ''),
        ], 'id = ?', [$id]);
        Article::log($id, 'wp_import', 'Lấy nội dung bài #' . $post['id']);
        flash('success', 'Đã lấy nội dung bài hiện tại từ WordPress. Khi đăng sẽ cập nhật đè lên bài cũ.');
        redirect('/articles/' . $id);
    }

    public function destroy(int $id): void
    {
        $user = Auth::requireLogin();
        [$article] = Article::findOrFail($id);
        if (!self::permissions($article, $user)['delete']) {
            abort(403);
        }
        Article::delete($id);
        flash('success', 'Đã xóa bài "' . $article['keyword'] . '". (Dòng trên Google Sheet cần xóa thủ công.)');
        redirect('/projects/' . $article['project_id']);
    }

    public function preview(int $id): void
    {
        Auth::requireLogin();
        [$article, $project] = Article::findOrFail($id);
        view('articles/preview', ['article' => $article, 'project' => $project, 'images' => Article::images($id)], null);
    }

    /** Thao tác hàng loạt trên danh sách bài. */
    public function bulk(int $projectId): void
    {
        $user = Auth::requireLogin();
        $project = Project::findOrFail($projectId);
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        if (!$ids) {
            throw new \RuntimeException('Chưa chọn bài viết nào.');
        }
        [$scope, $scopeParams] = Project::articleScope($user);
        $in = implode(',', array_fill(0, count($ids), '?'));
        $articles = db()->fetchAll("SELECT * FROM articles a WHERE a.project_id = ? AND a.id IN ($in) AND $scope", array_merge([$projectId], $ids, $scopeParams));
        $action = (string)input('action');
        $isManager = Auth::is('seo', 'leader');
        $ok = 0;
        $errors = [];

        foreach ($articles as $a) {
            try {
                if (isset(self::TASK_LABELS[$action])) {
                    Queue::articleTask($action, (int)$a['id'], (int)$user['id']);
                } elseif (str_starts_with($action, 'flow:')) {
                    Workflow::apply(substr($action, 5), (int)$a['id'], (string)input('note', ''));
                } elseif (str_starts_with($action, 'status:') && Auth::is('leader')) {
                    Article::log((int)$a['id'], 'status', 'Đổi thủ công (hàng loạt)');
                    Article::setStatus((int)$a['id'], substr($action, 7));
                } elseif (in_array($action, ['assign', 'assign_writer', 'assign_designer'], true) && $isManager) {
                    $field = ['assign' => 'assigned_to', 'assign_writer' => 'writer_id', 'assign_designer' => 'designer_id'][$action];
                    $uid = input_int($action) ?: null;
                    db()->update('articles', [$field => $uid], 'id = ?', [$a['id']]);
                    if ($uid) {
                        Article::log((int)$a['id'], 'assign', 'Giao ' . ['assigned_to' => 'SEO', 'writer_id' => 'Content', 'designer_id' => 'Design'][$field] . ': ' . db()->value('SELECT name FROM users WHERE id = ?', [$uid]));
                    }
                } elseif ($action === 'delete' && $isManager) {
                    Article::delete((int)$a['id']);
                } else {
                    throw new \RuntimeException('Bạn không thực hiện được thao tác này.');
                }
                $ok++;
            } catch (\RuntimeException $e) {
                $errors[] = '"' . $a['keyword'] . '": ' . $e->getMessage();
            }
        }
        if ($action === 'assign' && Project::hasSheet($project)) {
            Queue::push('sheet_push_all', [], ['project_id' => $projectId]);
        }
        flash($errors ? 'warning' : 'success', "Đã xử lý $ok bài." . ($errors ? ' Lỗi: ' . implode(' | ', array_slice(array_unique($errors), 0, 5)) : ''));
        back();
    }
}
