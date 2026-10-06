<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Csv;
use App\Models\Article;
use App\Models\Project;
use App\Queue;
use App\Workflow;

/** Giai đoạn tìm hiểu dự án: tổng quan, nghiên cứu, đối thủ, website, bộ từ khóa, KPI. */
class ResearchController
{
    public const SECTIONS = [
        'website_research' => ['Nghiên cứu website', 'Ngành, mô hình kinh doanh, cấu trúc website, điểm mạnh / điểm yếu hiện tại...'],
        'product_research' => ['Nghiên cứu sản phẩm / dịch vụ', 'Danh sách sản phẩm/dịch vụ chính, giá, USP, sản phẩm ưu tiên đẩy SEO...'],
        'customer' => ['Phân tích khách hàng', 'Chân dung khách hàng, độ tuổi, khu vực, nhu cầu, nỗi đau, rào cản khi mua...'],
        'behavior' => ['Phân tích hành vi người dùng', 'Cách khách tìm kiếm (từ khóa, câu hỏi), thiết bị, thời điểm, hành trình từ tìm hiểu đến mua...'],
    ];

    public const AUDIT_TEMPLATE = [
        'technical' => [
            'Website dùng HTTPS, không lỗi chứng chỉ, redirect http → https và www thống nhất',
            'Tốc độ tải trang (PageSpeed mobile, LCP < 2.5s)',
            'Giao diện thân thiện mobile',
            'robots.txt và sitemap.xml đúng, không chặn nhầm trang quan trọng',
            'Trang quan trọng đã được Google index (kiểm tra site:domain, Search Console)',
            'Thẻ canonical đúng, không trùng lặp title / meta',
            'Lỗi 404, link gãy, redirect chain',
            'Cấu trúc URL ngắn gọn, có từ khóa',
            'Schema (Organization, LocalBusiness, Product, Article, FAQ...)',
        ],
        'content' => [
            'Title / meta description các trang chính',
            'Cấu trúc heading H1 - H3 hợp lý',
            'Mô tả sản phẩm / dịch vụ đầy đủ, không sao chép',
            'Bài blog: chất lượng, độ dài, đúng search intent',
            'Bài blog trùng chủ đề (cannibalization) cần gộp / chuyển hướng',
            'Internal link giữa bài blog và trang sản phẩm / dịch vụ',
            'Hình ảnh có alt, dung lượng nhẹ',
        ],
        'ui' => [
            'Bố cục rõ ràng, dễ tìm thông tin',
            'CTA (hotline, form, chat) nổi bật',
            'Trải nghiệm trên mobile (menu, nút bấm, font chữ)',
            'Yếu tố tin cậy: giới thiệu, liên hệ, chứng nhận, đánh giá khách hàng',
        ],
    ];

    public const AUDIT_CATEGORIES = ['technical' => 'Technical', 'content' => 'Content', 'ui' => 'Giao diện'];
    public const AUDIT_STATUS = ['todo' => ['Chưa kiểm tra', 'secondary'], 'ok' => ['Đạt', 'success'], 'issue' => ['Cần tối ưu', 'danger'], 'na' => ['Không áp dụng', 'light']];
    public const VERDICTS = [
        'ok' => ['Website ổn – tối ưu lại những phần cần thiết', 'success'],
        'bad' => ['Website không ổn – cần tối ưu nhiều', 'warning'],
        'junk' => ['Website rác, không tối ưu được – đề xuất làm website mới', 'danger'],
    ];
    public const KPI_METRICS = [
        'traffic' => 'Traffic organic / tháng',
        'top3' => 'Số từ khóa top 3',
        'top10' => 'Số từ khóa top 10',
        'articles' => 'Số bài đăng',
        'backlinks' => 'Backlink mới',
        'leads' => 'Lead / đơn hàng từ SEO',
    ];

    private function project(int $id): array
    {
        // Content / Design chỉ làm việc trên bài được giao, không vào phần nghiên cứu
        Auth::requireRole('seo', 'leader');
        return Project::findOrFail($id);
    }

    private function sections(int $projectId): array
    {
        $out = [];
        foreach (db()->fetchAll('SELECT * FROM project_research WHERE project_id = ?', [$projectId]) as $r) {
            $out[$r['section']] = $r;
        }
        return $out;
    }

    // ------------------------------------------------------------ Tổng quan

    public function overview(int $id): void
    {
        $project = $this->project($id);
        $sections = $this->sections($id);
        $researchDone = count(array_filter(array_keys(self::SECTIONS), fn($k) => !empty($sections[$k]['is_done'])));
        $competitors = (int)db()->value('SELECT COUNT(*) FROM competitors WHERE project_id = ?', [$id]);
        $competitorData = (int)db()->value(
            'SELECT COUNT(DISTINCT c.id) FROM competitors c WHERE c.project_id = ? AND (EXISTS (SELECT 1 FROM competitor_keywords k WHERE k.competitor_id = c.id) OR EXISTS (SELECT 1 FROM competitor_metrics m WHERE m.competitor_id = c.id))',
            [$id]
        );
        $kw = db()->fetch('SELECT COUNT(*) total, SUM(cluster IS NOT NULL AND cluster <> \'\') clustered FROM keywords WHERE project_id = ?', [$id]);
        $kpiCount = (int)db()->value('SELECT COUNT(*) FROM kpis WHERE project_id = ?', [$id]);
        $counts = [];
        foreach (db()->fetchAll('SELECT status, COUNT(*) c FROM articles WHERE project_id = ? GROUP BY status', [$id]) as $r) {
            $counts[$r['status']] = (int)$r['c'];
        }
        $totalArticles = array_sum($counts);
        $published = ($counts['published'] ?? 0) + ($counts['wp_draft'] ?? 0);
        $due = (int)db()->value('SELECT COUNT(*) FROM articles a WHERE ' . Workflow::dueForReview($id, (int)$project['reoptimize_days']));
        $auditIssues = (int)db()->value("SELECT COUNT(*) FROM audit_items WHERE project_id = ? AND status = 'issue'", [$id]);
        $auditTodo = (int)db()->value("SELECT COUNT(*) FROM audit_items WHERE project_id = ? AND status = 'todo'", [$id]);
        $buildPlanDone = !empty($sections['build_plan']['is_done']);
        $base = '/projects/' . $id;

        $steps = [
            ['Tìm hiểu dự án', "$researchDone/" . count(self::SECTIONS) . ' mục nghiên cứu hoàn thành', $researchDone === count(self::SECTIONS), $base . '/research'],
            ['Phân tích đối thủ', $competitors ? "$competitors đối thủ, $competitorData có dữ liệu từ khóa/traffic" : 'Chưa thêm đối thủ', $competitorData > 0, $base . '/competitors'],
            $project['has_website']
                ? ['Kiểm tra website', $project['website_verdict'] ? self::VERDICTS[$project['website_verdict']][0] . " · $auditIssues mục cần tối ưu" : "Còn $auditTodo mục chưa kiểm tra", (bool)$project['website_verdict'], $base . '/website']
                : ['Kế hoạch build website', $buildPlanDone ? 'Đã có kế hoạch' : 'Chưa có website – cần lên kế hoạch build', $buildPlanDone, $base . '/website'],
            ['Bộ từ khóa', (int)$kw['total'] . ' từ khóa, ' . (int)$kw['clustered'] . ' đã gom nhóm chủ đề', (int)$kw['total'] > 0 && (int)$kw['clustered'] >= (int)$kw['total'], $base . '/keywords'],
            ['Lên KPI', $kpiCount ? "$kpiCount chỉ tiêu" : 'Chưa có KPI', $kpiCount > 0, $base . '/kpi'],
            ['Plan content', "$totalArticles bài trong kế hoạch", $totalArticles > 0, $base],
            ['Triển khai: outline → viết → duyệt → hình → đăng', "$published/$totalArticles bài đã đăng", $totalArticles > 0 && $published === $totalArticles, $base],
            ['Tối ưu lại bài cũ (' . (int)$project['reoptimize_days'] . ' ngày/lần)', $due ? "$due bài đến hạn kiểm tra" : 'Không có bài đến hạn', $due === 0 && $published > 0, $base . '?review=due'],
        ];

        view('projects/overview', [
            'pageTitle' => $project['name'],
            'project' => $project,
            'steps' => $steps,
            'counts' => $counts,
            'due' => $due,
            'tab' => 'overview',
        ]);
    }

    // ------------------------------------------------------------ Nghiên cứu

    public function research(int $id): void
    {
        $project = $this->project($id);
        view('projects/research', [
            'pageTitle' => 'Nghiên cứu – ' . $project['name'],
            'project' => $project,
            'sections' => $this->sections($id),
            'canEdit' => Auth::is('seo', 'leader'),
            'aiPending' => self::aiPending($id),
            'tab' => 'research',
        ]);
    }

    public function saveSection(int $id): void
    {
        $user = Auth::requireLogin();
        $this->project($id);
        $section = (string)input('section');
        if (!isset(self::SECTIONS[$section]) && $section !== 'build_plan') {
            throw new \RuntimeException('Mục không hợp lệ.');
        }
        db()->query(
            'REPLACE INTO project_research (project_id, section, content, is_done, updated_by) VALUES (?, ?, ?, ?, ?)',
            [$id, $section, (string)($_POST['content'] ?? ''), input('is_done') ? 1 : 0, $user['id']]
        );
        flash('success', 'Đã lưu.');
        redirect('/projects/' . $id . ($section === 'build_plan' ? '/website' : '/research') . '#' . $section);
    }

    // ------------------------------------------------------------ Đối thủ

    public function competitors(int $id): void
    {
        $project = $this->project($id);
        $competitors = db()->fetchAll(
            'SELECT c.*, (SELECT COUNT(*) FROM competitor_keywords k WHERE k.competitor_id = c.id) AS kw_count FROM competitors c WHERE c.project_id = ? ORDER BY c.id',
            [$id]
        );
        $metrics = [];
        foreach (db()->fetchAll('SELECT m.* FROM competitor_metrics m JOIN competitors c ON c.id = m.competitor_id WHERE c.project_id = ? ORDER BY m.period', [$id]) as $m) {
            $metrics[$m['competitor_id']][] = $m;
        }
        $selected = input_int('c') ?: (int)($competitors[0]['id'] ?? 0);
        $topKeywords = $selected ? db()->fetchAll(
            'SELECT k.*, (SELECT 1 FROM keywords p WHERE p.project_id = ? AND p.keyword = k.keyword) AS in_set
             FROM competitor_keywords k WHERE k.competitor_id = ? ORDER BY (k.position IS NULL), k.position <= 10 DESC, k.traffic DESC, k.volume DESC LIMIT 300',
            [$id, $selected]
        ) : [];
        view('projects/competitors', [
            'pageTitle' => 'Đối thủ – ' . $project['name'],
            'project' => $project,
            'competitors' => $competitors,
            'metrics' => $metrics,
            'selected' => $selected,
            'topKeywords' => $topKeywords,
            'canEdit' => Auth::is('seo', 'leader'),
            'aiPending' => self::aiPending($id),
            'tab' => 'competitors',
        ]);
    }

    public function storeCompetitor(int $id): void
    {
        $this->project($id);
        $added = 0;
        foreach (preg_split('~[\s,]+~', (string)input('domains', '')) as $domain) {
            $domain = strtolower(preg_replace('~^(https?://)?(www\.)?~i', '', trim($domain)) ?? '');
            $domain = explode('/', $domain)[0];
            if ($domain === '' || !str_contains($domain, '.')) {
                continue;
            }
            if (!db()->value('SELECT 1 FROM competitors WHERE project_id = ? AND domain = ?', [$id, $domain])) {
                db()->insert('competitors', ['project_id' => $id, 'domain' => $domain]);
                $added++;
            }
        }
        flash('success', "Đã thêm $added đối thủ.");
        redirect("/projects/$id/competitors");
    }

    private function competitor(int $cid): array
    {
        $c = db()->fetch('SELECT * FROM competitors WHERE id = ?', [$cid]) ?? abort(404);
        $this->project((int)$c['project_id']);
        return $c;
    }

    public function updateCompetitor(int $cid): void
    {
        $c = $this->competitor($cid);
        db()->update('competitors', [
            'started_at' => mb_substr((string)input('started_at', ''), 0, 20) ?: null,
            'backlink_notes' => (string)input('backlink_notes', '') ?: null,
            'content_notes' => (string)input('content_notes', '') ?: null,
        ], 'id = ?', [$cid]);
        flash('success', 'Đã lưu ghi chú đối thủ ' . $c['domain'] . '.');
        redirect('/projects/' . $c['project_id'] . '/competitors?c=' . $cid);
    }

    public function deleteCompetitor(int $cid): void
    {
        $c = $this->competitor($cid);
        db()->query('DELETE FROM competitor_keywords WHERE competitor_id = ?', [$cid]);
        db()->query('DELETE FROM competitor_metrics WHERE competitor_id = ?', [$cid]);
        db()->query('DELETE FROM competitors WHERE id = ?', [$cid]);
        flash('success', 'Đã xóa đối thủ ' . $c['domain'] . '.');
        redirect('/projects/' . $c['project_id'] . '/competitors');
    }

    /** Thêm số liệu theo tháng (traffic, số từ khóa, backlink...) */
    public function saveMetric(int $cid): void
    {
        $c = $this->competitor($cid);
        $period = (string)input('period', '');
        if (!preg_match('~^\d{4}-\d{2}$~', $period)) {
            throw new \RuntimeException('Tháng không hợp lệ.');
        }
        $data = [];
        foreach (['traffic', 'keywords', 'top10', 'referring_domains', 'backlinks'] as $f) {
            $data[$f] = Csv::int((string)input($f, ''));
        }
        db()->query(
            'INSERT INTO competitor_metrics (competitor_id, period, traffic, keywords, top10, referring_domains, backlinks) VALUES (?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE traffic = VALUES(traffic), keywords = VALUES(keywords), top10 = VALUES(top10), referring_domains = VALUES(referring_domains), backlinks = VALUES(backlinks)',
            array_merge([$cid, $period], array_values($data))
        );
        flash('success', 'Đã lưu số liệu tháng ' . $period . '.');
        redirect('/projects/' . $c['project_id'] . '/competitors?c=' . $cid);
    }

    /** Import từ khóa đối thủ từ file CSV (Ahrefs Organic keywords / Semrush Organic Positions). */
    public function importCompetitorKeywords(int $cid): void
    {
        $c = $this->competitor($cid);
        $rows = $this->uploadedCsv();
        $period = preg_match('~^\d{4}-\d{2}$~', (string)input('period')) ? (string)input('period') : date('Y-m');
        if (input('replace')) {
            db()->query('DELETE FROM competitor_keywords WHERE competitor_id = ?', [$cid]);
        }
        $n = 0;
        foreach ($rows as $r) {
            if (($r['keyword'] ?? '') === '') {
                continue;
            }
            db()->insert('competitor_keywords', [
                'competitor_id' => $cid,
                'keyword' => mb_substr($r['keyword'], 0, 255),
                'position' => Csv::int($r['position'] ?? null),
                'volume' => Csv::int($r['volume'] ?? null),
                'kd' => Csv::int($r['kd'] ?? null),
                'traffic' => Csv::int($r['traffic'] ?? null),
                'url' => isset($r['url']) ? mb_substr($r['url'], 0, 1000) : null,
                'period' => $period,
            ]);
            $n++;
        }
        if (!$n) {
            throw new \RuntimeException('Không đọc được từ khóa nào. File cần có cột "Keyword" (hoặc "Từ khóa").');
        }
        flash('success', "Đã import $n từ khóa của " . $c['domain'] . '.');
        redirect('/projects/' . $c['project_id'] . '/competitors?c=' . $cid);
    }

    /** Đưa từ khóa đối thủ đã chọn vào bộ từ khóa dự án. */
    public function competitorToKeywords(int $cid): void
    {
        $c = $this->competitor($cid);
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $n = 0;
        foreach ($ids as $kid) {
            $k = db()->fetch('SELECT * FROM competitor_keywords WHERE id = ? AND competitor_id = ?', [$kid, $cid]);
            if ($k) {
                $n += db()->query(
                    'INSERT IGNORE INTO keywords (project_id, keyword, volume, kd, source) VALUES (?, ?, ?, ?, ?)',
                    [$c['project_id'], $k['keyword'], $k['volume'], $k['kd'], 'Đối thủ: ' . $c['domain']]
                )->rowCount();
            }
        }
        flash('success', "Đã thêm $n từ khóa vào bộ từ khóa dự án.");
        redirect('/projects/' . $c['project_id'] . '/competitors?c=' . $cid);
    }

    private function uploadedCsv(): array
    {
        $file = $_FILES['file'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
            throw new \RuntimeException('Chưa chọn file CSV.');
        }
        return Csv::read($file['tmp_name']);
    }

    // ------------------------------------------------------------ Website

    public function website(int $id): void
    {
        $project = $this->project($id);
        if ($project['has_website'] && !db()->value('SELECT 1 FROM audit_items WHERE project_id = ?', [$id])) {
            $sort = 0;
            foreach (self::AUDIT_TEMPLATE as $cat => $items) {
                foreach ($items as $item) {
                    db()->insert('audit_items', ['project_id' => $id, 'category' => $cat, 'item' => $item, 'sort' => $sort++]);
                }
            }
        }
        $items = [];
        foreach (db()->fetchAll('SELECT * FROM audit_items WHERE project_id = ? ORDER BY sort, id', [$id]) as $it) {
            $items[$it['category']][] = $it;
        }
        view('projects/website', [
            'pageTitle' => 'Website – ' . $project['name'],
            'project' => $project,
            'items' => $items,
            'sections' => $this->sections($id),
            'canEdit' => Auth::is('seo', 'leader'),
            'aiPending' => self::aiPending($id),
            'tab' => 'website',
        ]);
    }

    public function saveWebsite(int $id): void
    {
        $this->project($id);
        $verdict = (string)input('website_verdict', '');
        db()->update('projects', [
            'has_website' => input('has_website') ? 1 : 0,
            'website_verdict' => isset(self::VERDICTS[$verdict]) ? $verdict : null,
            'website_proposal' => (string)input('website_proposal', '') ?: null,
        ], 'id = ?', [$id]);
        foreach ((array)($_POST['audit'] ?? []) as $itemId => $row) {
            $status = isset(self::AUDIT_STATUS[$row['status'] ?? '']) ? $row['status'] : 'todo';
            db()->update('audit_items', ['status' => $status, 'note' => trim((string)($row['note'] ?? '')) ?: null], 'id = ? AND project_id = ?', [(int)$itemId, $id]);
        }
        $newItem = trim((string)input('new_item', ''));
        if ($newItem !== '' && isset(self::AUDIT_CATEGORIES[input('new_category')])) {
            db()->insert('audit_items', ['project_id' => $id, 'category' => input('new_category'), 'item' => mb_substr($newItem, 0, 255), 'sort' => 999]);
        }
        flash('success', 'Đã lưu kết quả kiểm tra website.');
        redirect("/projects/$id/website");
    }

    // ------------------------------------------------------------ Bộ từ khóa

    public function keywords(int $id): void
    {
        $project = $this->project($id);
        $where = 'k.project_id = ?';
        $params = [$id];
        $cluster = (string)input('cluster', '');
        if ($cluster === '__none') {
            $where .= " AND (k.cluster IS NULL OR k.cluster = '')";
        } elseif ($cluster !== '') {
            $where .= ' AND k.cluster = ?';
            $params[] = $cluster;
        }
        $q = (string)input('q', '');
        if ($q !== '') {
            $where .= ' AND k.keyword LIKE ?';
            $params[] = "%$q%";
        }
        $keywords = db()->fetchAll(
            "SELECT k.*, a.status AS article_status FROM keywords k LEFT JOIN articles a ON a.id = k.article_id
             WHERE $where ORDER BY k.cluster IS NULL, k.cluster, k.priority, k.volume DESC LIMIT 2000",
            $params
        );
        $clusters = db()->fetchAll(
            "SELECT cluster, COUNT(*) c, SUM(volume) vol, SUM(article_id IS NOT NULL) planned FROM keywords
             WHERE project_id = ? AND cluster IS NOT NULL AND cluster <> '' GROUP BY cluster ORDER BY vol DESC",
            [$id]
        );
        view('projects/keywords', [
            'pageTitle' => 'Bộ từ khóa – ' . $project['name'],
            'project' => $project,
            'keywords' => $keywords,
            'clusters' => $clusters,
            'total' => (int)db()->value('SELECT COUNT(*) FROM keywords WHERE project_id = ?', [$id]),
            'unclustered' => (int)db()->value("SELECT COUNT(*) FROM keywords WHERE project_id = ? AND (cluster IS NULL OR cluster = '')", [$id]),
            'clusterJob' => Queue::projectHasPending($id, 'cluster_keywords'),
            'canEdit' => Auth::is('seo', 'leader'),
            'aiPending' => self::aiPending($id),
            'tab' => 'keywords',
        ]);
    }

    /** Thêm từ khóa: nhập tay (mỗi dòng: từ khóa | volume | nhóm) hoặc import CSV. */
    public function storeKeywords(int $id): void
    {
        $this->project($id);
        $rows = [];
        if (!empty($_FILES['file']['tmp_name'])) {
            $rows = $this->uploadedCsv();
        } else {
            foreach (preg_split('~\R~', (string)input('lines', '')) as $line) {
                $p = array_map('trim', explode('|', $line));
                if (($p[0] ?? '') !== '') {
                    $rows[] = ['keyword' => $p[0], 'volume' => $p[1] ?? '', 'cluster' => $p[2] ?? ''];
                }
            }
        }
        $n = 0;
        foreach ($rows as $r) {
            $kw = mb_substr(trim($r['keyword'] ?? ''), 0, 255);
            if ($kw === '') {
                continue;
            }
            $n += db()->query(
                'INSERT INTO keywords (project_id, keyword, volume, kd, intent, cluster, target_url, current_rank, source) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE volume = COALESCE(VALUES(volume), volume), kd = COALESCE(VALUES(kd), kd),
                   cluster = COALESCE(VALUES(cluster), cluster), current_rank = COALESCE(VALUES(current_rank), current_rank)',
                [
                    $id, $kw, Csv::int($r['volume'] ?? null), Csv::int($r['kd'] ?? null), ($r['intent'] ?? '') ?: null,
                    ($r['cluster'] ?? '') ?: null, ($r['url'] ?? '') ?: null, Csv::int($r['position'] ?? null),
                    empty($_FILES['file']['tmp_name']) ? 'Nhập tay' : 'Import CSV',
                ]
            )->rowCount() > 0 ? 1 : 0;
        }
        flash('success', "Đã thêm / cập nhật $n từ khóa.");
        redirect("/projects/$id/keywords");
    }

    /** Thao tác hàng loạt: gán nhóm, độ ưu tiên, intent, tạo bài vào kế hoạch, xóa. */
    public function bulkKeywords(int $id): void
    {
        $user = Auth::requireLogin();
        $project = $this->project($id);
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));
        $action = (string)input('action');
        if ($action === 'cluster_ai') {
            if (!Queue::projectHasPending($id, 'cluster_keywords')) {
                \App\Usage::assertWithinBudget((int)$user['id']);
                Queue::push('cluster_keywords', ['only_missing' => input('only_missing') ? 1 : 0, 'ai' => chosen_ai()], ['project_id' => $id, 'user_id' => $user['id']]);
            }
            flash('info', 'AI đang gom nhóm chủ đề (khoảng 1-2 phút). Tải lại trang sau ít phút.');
            redirect("/projects/$id/keywords");
        }
        if (!$ids) {
            throw new \RuntimeException('Chưa chọn từ khóa nào.');
        }
        $in = implode(',', array_fill(0, count($ids), '?'));
        $rows = db()->fetchAll("SELECT * FROM keywords WHERE project_id = ? AND id IN ($in)", array_merge([$id], $ids));
        $n = 0;
        foreach ($rows as $k) {
            switch ($action) {
                case 'set_cluster':
                    db()->update('keywords', ['cluster' => mb_substr((string)input('value', ''), 0, 190) ?: null], 'id = ?', [$k['id']]);
                    break;
                case 'set_priority':
                    db()->update('keywords', ['priority' => max(1, min(3, (int)input('value', 2)))], 'id = ?', [$k['id']]);
                    break;
                case 'set_intent':
                    db()->update('keywords', ['intent' => mb_substr((string)input('value', ''), 0, 30) ?: null], 'id = ?', [$k['id']]);
                    break;
                case 'plan':
                    if ($k['article_id']) {
                        continue 2;
                    }
                    $articleId = Article::create($project, [
                        'keyword' => $k['keyword'],
                        'cluster' => $k['cluster'],
                        'assigned_to' => $user['id'],
                        'type' => $k['target_url'] ? 'optimize' : 'new',
                        'wp_url' => $k['target_url'],
                        'planned_date' => preg_match('~^\d{4}-\d{2}-\d{2}$~', (string)input('value')) ? input('value') : null,
                    ]);
                    Article::log($articleId, 'created', 'Tạo từ bộ từ khóa' . ($k['cluster'] ? ' (nhóm: ' . $k['cluster'] . ')' : ''));
                    db()->update('keywords', ['article_id' => $articleId], 'id = ?', [$k['id']]);
                    break;
                case 'delete':
                    db()->query('DELETE FROM keywords WHERE id = ?', [$k['id']]);
                    break;
                default:
                    throw new \RuntimeException('Thao tác không hợp lệ.');
            }
            $n++;
        }
        if ($action === 'plan' && $n && Project::hasSheet($project)) {
            Queue::push('sheet_push_all', [], ['project_id' => $id]);
        }
        flash('success', $action === 'plan' ? "Đã tạo $n bài trong kế hoạch content." : "Đã cập nhật $n từ khóa.");
        redirect("/projects/$id/keywords" . query_with([]));
    }

    // ------------------------------------------------------------ AI hỗ trợ

    /** Các tác vụ AI đang chờ / đang chạy của dự án, dạng "ai_research:customer", "ai_competitor:3", "ai_audit"... */
    public static function aiPending(int $projectId): array
    {
        $keys = [];
        $jobs = db()->fetchAll(
            "SELECT type, payload FROM jobs WHERE project_id = ? AND status IN ('pending','running') AND (type LIKE 'ai!_%' ESCAPE '!' OR type = 'cluster_keywords')",
            [$projectId]
        );
        foreach ($jobs as $j) {
            $p = json_decode((string)$j['payload'], true) ?: [];
            $keys[] = $j['type'];
            if (isset($p['section'])) {
                $keys[] = $j['type'] . ':' . $p['section'];
            }
            if (isset($p['competitor_id'])) {
                $keys[] = $j['type'] . ':' . $p['competitor_id'];
            }
        }
        return array_values(array_unique($keys));
    }

    /** Bấm nút "AI làm" ở các bước nghiên cứu: đưa tác vụ vào hàng đợi. */
    public function ai(int $id): void
    {
        $user = Auth::requireRole('seo', 'leader');
        $project = $this->project($id);
        $task = (string)input('task');
        if (!isset(\App\Services\ResearchAi::TASKS[$task])) {
            throw new \RuntimeException('Tác vụ AI không hợp lệ.');
        }
        if (!\App\Services\AiText::ready()) {
            throw new \RuntimeException('Chưa cấu hình AI (API key) trong Cài đặt hệ thống.');
        }
        $payload = ['ai' => chosen_ai()];
        $key = $task;
        $back = "/projects/$id";
        switch ($task) {
            case 'ai_research':
                $section = (string)input('section');
                if (!isset(self::SECTIONS[$section]) && $section !== 'build_plan') {
                    throw new \RuntimeException('Mục nghiên cứu không hợp lệ.');
                }
                $payload['section'] = $section;
                $key .= ':' . $section;
                $back .= ($section === 'build_plan' ? '/website' : '/research') . '#' . $section;
                break;
            case 'ai_competitor':
                $cid = (int)input('competitor_id');
                if (!db()->value('SELECT 1 FROM competitors WHERE id = ? AND project_id = ?', [$cid, $id])) {
                    throw new \RuntimeException('Đối thủ không tồn tại.');
                }
                $payload['competitor_id'] = $cid;
                $key .= ':' . $cid;
                $back .= '/competitors?c=' . $cid;
                break;
            case 'ai_audit':
                if (!$project['wp_url'] && !$project['domain']) {
                    throw new \RuntimeException('Dự án chưa nhập domain website (Cài đặt dự án).');
                }
                $payload['overwrite'] = input('overwrite') ? 1 : 0;
                $back .= '/website';
                break;
            case 'ai_keywords':
                $payload['count'] = max(10, min(100, (int)input('count', 40)));
                $back .= '/keywords';
                break;
            case 'ai_kpi':
                $payload['months'] = max(1, min(24, (int)input('months', 6)));
                $back .= '/kpi';
                break;
            case 'ai_content_audit':
                $payload['limit'] = 20;
                $back .= '/links?audit=__none';
                break;
        }
        if (in_array($key, self::aiPending($id), true)) {
            flash('info', 'Tác vụ này đang chạy, vui lòng đợi.');
            redirect($back);
        }
        \App\Usage::assertWithinBudget((int)$user['id']);
        Queue::push($task, $payload, ['project_id' => $id, 'user_id' => $user['id']]);
        flash('info', \App\Services\ResearchAi::TASKS[$task] . ': đã đưa vào hàng đợi, khoảng 1-3 phút. Trang sẽ tự tải lại khi xong (nếu bạn không đang nhập liệu).');
        redirect($back);
    }

    // ------------------------------------------------------------ KPI

    public function kpi(int $id): void
    {
        $project = $this->project($id);
        $rows = db()->fetchAll('SELECT * FROM kpis WHERE project_id = ? ORDER BY period, metric', [$id]);
        $grid = [];
        foreach ($rows as $r) {
            $grid[$r['period']][$r['metric']] = $r;
        }
        // Số bài đăng thực tế tính tự động
        $published = [];
        foreach (db()->fetchAll("SELECT DATE_FORMAT(published_at, '%Y-%m') p, COUNT(*) c FROM articles WHERE project_id = ? AND published_at IS NOT NULL GROUP BY p", [$id]) as $r) {
            $published[$r['p']] = (int)$r['c'];
        }
        ksort($grid);
        view('projects/kpi', [
            'pageTitle' => 'KPI – ' . $project['name'],
            'project' => $project,
            'grid' => $grid,
            'published' => $published,
            'kpiNote' => (string)db()->value("SELECT content FROM project_research WHERE project_id = ? AND section = 'kpi_note'", [$id]),
            'canEdit' => Auth::is('seo', 'leader'),
            'aiPending' => self::aiPending($id),
            'tab' => 'kpi',
        ]);
    }

    public function saveKpi(int $id): void
    {
        $this->project($id);
        // Thêm các tháng mới
        $from = (string)input('from', '');
        $months = max(0, min(24, (int)input('months', 0)));
        if (preg_match('~^\d{4}-\d{2}$~', $from) && $months > 0) {
            for ($i = 0; $i < $months; $i++) {
                $period = date('Y-m', strtotime($from . '-01 +' . $i . ' month'));
                foreach (array_keys(self::KPI_METRICS) as $metric) {
                    db()->query('INSERT IGNORE INTO kpis (project_id, period, metric) VALUES (?, ?, ?)', [$id, $period, $metric]);
                }
            }
        }
        foreach ((array)($_POST['kpi'] ?? []) as $kpiId => $row) {
            $num = fn($v) => ($v === '' || $v === null) ? null : (float)str_replace(',', '', (string)$v);
            db()->update('kpis', ['target' => $num($row['target'] ?? null), 'actual' => $num($row['actual'] ?? null)], 'id = ? AND project_id = ?', [(int)$kpiId, $id]);
        }
        if ($del = (string)input('delete_period', '')) {
            db()->query('DELETE FROM kpis WHERE project_id = ? AND period = ?', [$id, $del]);
        }
        flash('success', 'Đã lưu KPI.');
        redirect("/projects/$id/kpi");
    }
}
