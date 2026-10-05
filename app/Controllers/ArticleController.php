<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Models\Article;
use App\Models\Project;
use App\Queue;
use App\Services\ContentWriter;
use App\Settings;

class ArticleController
{
    private const TASK_LABELS = [
        'outline' => 'tạo outline',
        'write' => 'viết bài',
        'images' => 'tạo ảnh',
        'publish' => 'đăng WordPress',
    ];

    public function create(int $projectId): void
    {
        Auth::requireLogin();
        $project = Project::findOrFail($projectId);
        view('articles/create', [
            'pageTitle' => 'Thêm bài viết',
            'project' => $project,
            'assignees' => Project::assignableUsers($project),
            'tab' => 'articles',
        ]);
    }

    public function store(int $projectId): void
    {
        $user = Auth::requireLogin();
        $project = Project::findOrFail($projectId);
        $assignee = input_int('assigned_to') ?: (int)$user['id'];
        $wordCount = input_int('word_count') ?: null;
        $ids = [];

        if (input('mode') === 'bulk') {
            // Mỗi dòng: từ khóa chính | từ khóa phụ | ghi chú
            foreach (preg_split('~\R~', (string)input('lines', '')) as $line) {
                $parts = array_map('trim', explode('|', $line, 3));
                if (($parts[0] ?? '') === '') {
                    continue;
                }
                $ids[] = Article::create($project, [
                    'keyword' => $parts[0],
                    'secondary_keywords' => ($parts[1] ?? '') ?: null,
                    'notes' => ($parts[2] ?? '') ?: null,
                    'assigned_to' => $assignee,
                    'word_count' => $wordCount,
                ]);
            }
        } else {
            $keyword = (string)input('keyword', '');
            if ($keyword === '') {
                throw new \RuntimeException('Vui lòng nhập từ khóa chính.');
            }
            $ids[] = Article::create($project, [
                'keyword' => $keyword,
                'secondary_keywords' => (string)input('secondary_keywords', '') ?: null,
                'notes' => (string)input('notes', '') ?: null,
                'title' => (string)input('title', '') ?: null,
                'assigned_to' => $assignee,
                'word_count' => $wordCount,
            ]);
        }
        if (!$ids) {
            throw new \RuntimeException('Chưa có từ khóa nào hợp lệ.');
        }

        $task = (string)input('then', '');
        $queued = 0;
        if (in_array($task, ['outline', 'write'], true)) {
            foreach ($ids as $id) {
                Queue::articleTask($task, $id, (int)$user['id']);
                $queued++;
            }
        }
        if (Project::hasSheet($project)) {
            count($ids) === 1
                ? Article::syncToSheet($ids[0])
                : Queue::push('sheet_push_all', [], ['project_id' => $projectId]);
        }

        $msg = 'Đã thêm ' . count($ids) . ' bài viết.';
        if ($queued) {
            $msg .= " $queued bài đã vào hàng đợi " . self::TASK_LABELS[$task] . '.';
        }
        flash('success', $msg);
        redirect(count($ids) === 1 ? '/articles/' . $ids[0] : '/projects/' . $projectId);
    }

    public function edit(int $id): void
    {
        Auth::requireLogin();
        [$article, $project] = Article::findOrFail($id);
        view('articles/edit', [
            'pageTitle' => $article['title'] ?: $article['keyword'],
            'project' => $project,
            'article' => $article,
            'images' => Article::images($id),
            'assignees' => Project::assignableUsers($project),
            'hasClaude' => Settings::has('anthropic_api_key'),
            'hasOpenAI' => Settings::has('openai_api_key'),
            'linkCount' => (int)db()->value('SELECT COUNT(*) FROM internal_links WHERE project_id = ?', [$project['id']]),
            'tab' => 'articles',
        ]);
    }

    public function update(int $id): void
    {
        Auth::requireLogin();
        [$article, $project] = Article::findOrFail($id);
        if (in_array($article['ai_state'], ['queued', 'running'], true) && in_array($article['ai_task'], ['write', 'outline'], true)) {
            throw new \RuntimeException('AI đang xử lý bài này, vui lòng đợi xong rồi lưu.');
        }
        $keyword = (string)input('keyword', '');
        if ($keyword === '') {
            throw new \RuntimeException('Từ khóa chính không được để trống.');
        }
        $data = [
            'keyword' => mb_substr($keyword, 0, 255),
            'secondary_keywords' => (string)input('secondary_keywords', '') ?: null,
            'notes' => (string)input('notes', '') ?: null,
            'word_count' => input_int('word_count') ?: null,
            'title' => mb_substr((string)input('title', ''), 0, 500) ?: null,
            'slug' => slugify((string)input('slug', '')) ?: null,
            'meta_title' => mb_substr((string)input('meta_title', ''), 0, 255) ?: null,
            'meta_description' => mb_substr((string)input('meta_description', ''), 0, 500) ?: null,
            'outline' => (string)input('outline', '') ?: null,
            'content' => ContentWriter::sanitizeHtml((string)($_POST['content'] ?? '')) ?: null,
            'assigned_to' => input_int('assigned_to') ?: null,
            'wp_category_id' => input_int('wp_category_id') ?: null,
        ];
        foreach ((array)($_POST['image_alt'] ?? []) as $imgId => $alt) {
            db()->update('article_images', ['alt_text' => mb_substr(trim((string)$alt), 0, 250)], 'id = ? AND article_id = ?', [(int)$imgId, $id]);
        }
        db()->update('articles', $data, 'id = ?', [$id]);

        $status = (string)input('status', $article['status']);
        if ($status !== $article['status']) {
            Article::setStatus($id, $status);
        } elseif ($data['title'] !== $article['title'] || $data['keyword'] !== $article['keyword'] || (int)$data['assigned_to'] !== (int)$article['assigned_to']) {
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

    public function status(int $id): void
    {
        Auth::requireLogin();
        Article::findOrFail($id);
        Article::setStatus($id, (string)input('status'));
        if (wants_json()) {
            json_response(['ok' => true]);
        }
        back();
    }

    public function destroy(int $id): void
    {
        Auth::requireLogin();
        [$article] = Article::findOrFail($id);
        Article::delete($id);
        flash('success', 'Đã xóa bài "' . $article['keyword'] . '". (Dòng trên Google Sheet cần xóa thủ công.)');
        redirect('/projects/' . $article['project_id']);
    }

    public function regenerateImage(int $id, int $imageId): void
    {
        $user = Auth::requireLogin();
        Article::findOrFail($id);
        $prompt = trim((string)input('prompt', ''));
        Queue::articleTask('image', $id, (int)$user['id'], ['image_id' => $imageId, 'prompt' => $prompt ?: null]);
        flash('info', 'Đang tạo lại ảnh...');
        redirect('/articles/' . $id . '#images');
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
        $in = implode(',', array_fill(0, count($ids), '?'));
        $articles = db()->fetchAll("SELECT * FROM articles WHERE project_id = ? AND id IN ($in)", array_merge([$projectId], $ids));
        $action = (string)input('action');
        $ok = 0;
        $errors = [];

        foreach ($articles as $a) {
            try {
                if (isset(self::TASK_LABELS[$action])) {
                    Queue::articleTask($action, (int)$a['id'], (int)$user['id']);
                } elseif (str_starts_with($action, 'status:')) {
                    Article::setStatus((int)$a['id'], substr($action, 7));
                } elseif ($action === 'assign') {
                    db()->update('articles', ['assigned_to' => input_int('assignee') ?: null], 'id = ?', [$a['id']]);
                } elseif ($action === 'delete') {
                    Article::delete((int)$a['id']);
                } else {
                    throw new \RuntimeException('Thao tác không hợp lệ.');
                }
                $ok++;
            } catch (\RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($action === 'assign' && Project::hasSheet($project)) {
            Queue::push('sheet_push_all', [], ['project_id' => $projectId]);
        }
        flash($errors ? 'warning' : 'success', "Đã xử lý $ok bài." . ($errors ? ' Lỗi: ' . implode(' | ', array_unique($errors)) : ''));
        back();
    }
}
