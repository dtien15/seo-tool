<?php
declare(strict_types=1);

namespace App\Services;

use App\Controllers\LinkController;
use App\Controllers\ResearchController as R;

/**
 * AI hỗ trợ các bước nghiên cứu: viết nháp nghiên cứu, phân tích đối thủ, kiểm tra website,
 * gợi ý từ khóa, đề xuất KPI, rà soát nội dung cũ.
 * Nguyên tắc: AI chỉ điền nháp vào chỗ còn trống hoặc nối thêm, không xóa nội dung người dùng đã nhập.
 */
class ResearchAi
{
    public const TASKS = [
        'ai_research' => 'AI viết nháp nghiên cứu',
        'ai_competitor' => 'AI phân tích đối thủ',
        'ai_audit' => 'AI kiểm tra website',
        'ai_keywords' => 'AI gợi ý từ khóa',
        'ai_kpi' => 'AI đề xuất KPI',
        'ai_content_audit' => 'AI rà soát nội dung web',
    ];

    private static function system(array $project): string
    {
        $brief = [];
        foreach (['name' => 'Thương hiệu', 'domain' => 'Website', 'niche' => 'Lĩnh vực', 'target_audience' => 'Đối tượng', 'main_keywords' => 'Từ khóa chính', 'content_rules' => 'Quy tắc nội dung'] as $k => $label) {
            if (trim((string)($project[$k] ?? '')) !== '') {
                $brief[] = "- $label: " . trim((string)$project[$k]);
            }
        }
        return "Bạn là chuyên gia SEO và marketing tại Việt Nam, làm việc cho dự án dưới đây.\n\nThông tin dự án:\n"
            . ($brief ? implode("\n", $brief) : '- (chưa có)')
            . "\n\nNguyên tắc: viết tiếng Việt, ngắn gọn, gạch đầu dòng, thực tế. Chỉ dựa trên dữ liệu được cung cấp;"
            . " điều gì là suy luận thì ghi rõ \"(giả định – cần kiểm chứng)\". Không bịa số liệu.";
    }

    private static function siteBase(array $project): string
    {
        $base = SiteReader::base((string)($project['wp_url'] ?: $project['domain']));
        if ($base === '') {
            throw new \RuntimeException('Dự án chưa nhập domain website (Cài đặt dự án).');
        }
        return $base;
    }

    private static function section(int $projectId, string $key): string
    {
        return (string)db()->value('SELECT content FROM project_research WHERE project_id = ? AND section = ?', [$projectId, $key]);
    }

    private static function topKeywords(int $projectId, int $limit = 60): string
    {
        $rows = db()->fetchAll('SELECT keyword, volume, cluster FROM keywords WHERE project_id = ? ORDER BY volume DESC LIMIT ' . $limit, [$projectId]);
        return implode("\n", array_map(fn($r) => '- ' . $r['keyword'] . ($r['volume'] ? " ({$r['volume']})" : '') . ($r['cluster'] ? " [{$r['cluster']}]" : ''), $rows));
    }

    /** Nối nháp AI vào nội dung có sẵn (không ghi đè). */
    private static function merge(string $old, string $new): string
    {
        $old = trim($old);
        return $old === '' ? trim($new) : $old . "\n\n--- Gợi ý của AI (" . date('d/m/Y') . ") ---\n" . trim($new);
    }

    // ------------------------------------------------------------------ Nghiên cứu

    public static function research(array $project, string $section, array $ctx): void
    {
        $titles = R::SECTIONS + ['build_plan' => ['Kế hoạch build website', '']];
        if (!isset($titles[$section])) {
            throw new \RuntimeException('Mục nghiên cứu không hợp lệ.');
        }
        $data = '';
        if ($section !== 'build_plan' && ($project['wp_url'] || $project['domain'])) {
            foreach (SiteReader::sample(self::siteBase($project), 4, 2500) as $page) {
                $data .= "\n\n" . SiteReader::summarize($page);
            }
        }
        $other = '';
        foreach (array_keys($titles) as $k) {
            if ($k !== $section && ($c = trim(self::section((int)$project['id'], $k))) !== '') {
                $other .= "\n\n## {$titles[$k][0]}\n" . mb_substr($c, 0, 1500);
            }
        }
        $keywords = self::topKeywords((int)$project['id']);
        $guide = [
            'website_research' => 'Phân tích website: ngành và mô hình kinh doanh, cấu trúc website (menu, nhóm trang chính), loại nội dung đang có, điểm mạnh, điểm yếu về SEO / nội dung / chuyển đổi.',
            'product_research' => 'Liệt kê nhóm sản phẩm / dịch vụ chính, sản phẩm nổi bật, điểm khác biệt (USP), sản phẩm nên ưu tiên đẩy SEO và lý do.',
            'customer' => 'Chân dung 2-4 nhóm khách hàng: ai, độ tuổi, khu vực, nhu cầu, nỗi đau, rào cản khi mua, điều họ cần thấy để tin tưởng.',
            'behavior' => 'Hành vi tìm kiếm & mua: khách tìm bằng từ khóa / câu hỏi gì ở từng giai đoạn (tìm hiểu → so sánh → mua), thiết bị, thời điểm / mùa vụ, kênh tham khảo. Gợi ý loại nội dung cho từng giai đoạn.',
            'build_plan' => 'Kế hoạch build website mới: nền tảng đề xuất, cấu trúc trang (sitemap) dựa trên bộ từ khóa và sản phẩm, trang dịch vụ / danh mục / blog, yêu cầu technical SEO, các giai đoạn và thời gian dự kiến.',
        ][$section];

        $prompt = "Viết nháp mục \"{$titles[$section][0]}\" cho giai đoạn tìm hiểu dự án SEO.\nYêu cầu: $guide\n"
            . ($data ? "\nDữ liệu đọc từ website của dự án:$data\n" : "\n(Không đọc được website – dựa vào thông tin dự án.)\n")
            . ($keywords ? "\nBộ từ khóa hiện có (volume, [nhóm]):\n$keywords\n" : '')
            . ($other ? "\nCác mục nghiên cứu khác đã có:$other\n" : '')
            . "\nTrả lời đúng định dạng:\n<result>\n...nội dung, gạch đầu dòng...\n</result>";
        $res = AiText::complete(self::system($project), $prompt, 6000, $ctx, 'research');
        $text = ContentWriter::tag($res['text'], 'result') ?? trim($res['text']);
        db()->query(
            'INSERT INTO project_research (project_id, section, content, is_done, updated_by) VALUES (?, ?, ?, 0, ?)
             ON DUPLICATE KEY UPDATE content = VALUES(content), updated_by = VALUES(updated_by)',
            [$project['id'], $section, self::merge(self::section((int)$project['id'], $section), $text), $ctx['user_id']]
        );
    }

    // ------------------------------------------------------------------ Đối thủ

    public static function competitor(array $project, int $competitorId, array $ctx): void
    {
        $c = db()->fetch('SELECT * FROM competitors WHERE id = ? AND project_id = ?', [$competitorId, $project['id']]);
        if (!$c) {
            throw new \RuntimeException('Đối thủ không tồn tại.');
        }
        $pages = '';
        foreach (SiteReader::sample(SiteReader::base($c['domain']), 4, 2000) as $page) {
            $pages .= "\n\n" . SiteReader::summarize($page);
        }
        $kw = db()->fetchAll('SELECT keyword, position, volume, traffic, url FROM competitor_keywords WHERE competitor_id = ? ORDER BY traffic DESC, volume DESC LIMIT 40', [$competitorId]);
        $kwText = implode("\n", array_map(fn($k) => "- {$k['keyword']} | top {$k['position']} | vol {$k['volume']} | traffic {$k['traffic']} | {$k['url']}", $kw));
        $metrics = db()->fetchAll('SELECT * FROM competitor_metrics WHERE competitor_id = ? ORDER BY period', [$competitorId]);
        $mText = implode("\n", array_map(fn($m) => "- {$m['period']}: traffic {$m['traffic']}, từ khóa {$m['keywords']}, top10 {$m['top10']}, ref.domains {$m['referring_domains']}", $metrics));

        $prompt = "Phân tích website đối thủ {$c['domain']} để lên chiến lược SEO cạnh tranh.\n"
            . "\nDữ liệu đọc từ website đối thủ:$pages\n"
            . ($kwText ? "\nTừ khóa đối thủ đang lên top:\n$kwText\n" : '')
            . ($mText ? "\nSố liệu theo tháng:\n$mText\n" : '')
            . "\nViết 2 phần:\n<content>\nCheck content web đối thủ: cấu trúc web, loại nội dung, độ dài / độ sâu bài, cách trình bày, CTA; điểm mạnh nên học; điểm yếu / khoảng trống nội dung mình có thể vượt.\n</content>\n"
            . "<backlink>\nNhận xét về sức mạnh SEO / backlink dựa trên số liệu có (nếu không có số liệu thì nêu những gì cần kiểm tra thêm trong Ahrefs/Semrush).\n</backlink>";
        $res = AiText::complete(self::system($project), $prompt, 6000, $ctx, 'competitor');
        db()->update('competitors', [
            'content_notes' => self::merge((string)$c['content_notes'], (string)(ContentWriter::tag($res['text'], 'content') ?? $res['text'])),
            'backlink_notes' => ($b = ContentWriter::tag($res['text'], 'backlink')) ? self::merge((string)$c['backlink_notes'], $b) : $c['backlink_notes'],
        ], 'id = ?', [$competitorId]);
    }

    // ------------------------------------------------------------------ Kiểm tra website

    public static function audit(array $project, bool $overwrite, array $ctx): void
    {
        $base = self::siteBase($project);
        $result = SiteAudit::run($base);
        $pages = '';
        foreach (array_slice(SiteReader::sample($base, 3, 1500), 1) as $page) {
            $pages .= "\n\n" . SiteReader::summarize($page);
        }
        $items = db()->fetchAll('SELECT id, category, item, status FROM audit_items WHERE project_id = ? ORDER BY sort, id', [$project['id']]);
        $itemText = implode("\n", array_map(fn($i) => "{$i['id']} | " . R::AUDIT_CATEGORIES[$i['category']] . " | {$i['item']}", $items));

        $prompt = "Kiểm tra website $base theo checklist SEO.\n\nKết quả kiểm tra kỹ thuật tự động:\n" . SiteAudit::toText($result)
            . "\n\nTrang chủ:\n" . SiteReader::summarize($result['home']) . ($pages ? "\n\nMột số trang con:$pages" : '')
            . "\n\nChecklist (id | nhóm | mục):\n$itemText\n"
            . "\nVới MỖI mục trong checklist, đánh giá: ok (đạt), issue (cần tối ưu), na (không đủ dữ liệu / không áp dụng)."
            . " Ghi chú ngắn bằng chứng và việc cần làm. Sau đó kết luận website: ok (ổn – tối ưu lại phần cần thiết), bad (không ổn – cần tối ưu nhiều),"
            . " junk (website rác, không tối ưu được – nên làm web mới), kèm phương án đề xuất theo thứ tự ưu tiên.\n"
            . "\nTrả lời đúng định dạng:\n<items>\nid | ok/issue/na | ghi chú\n</items>\n<verdict>ok|bad|junk</verdict>\n<proposal>\n- việc cần làm...\n</proposal>";
        $res = AiText::complete(self::system($project), $prompt, 8000, $ctx, 'audit');

        $byId = [];
        foreach ($items as $i) {
            $byId[(int)$i['id']] = $i;
        }
        foreach (preg_split('~\R~', (string)ContentWriter::tag($res['text'], 'items')) as $line) {
            $p = array_map('trim', explode('|', $line, 3));
            $id = (int)preg_replace('~\D~', '', $p[0] ?? '');
            $status = strtolower($p[1] ?? '');
            if (!isset($byId[$id]) || !in_array($status, ['ok', 'issue', 'na'], true)) {
                continue;
            }
            if ($overwrite || $byId[$id]['status'] === 'todo') {
                db()->update('audit_items', ['status' => $status, 'note' => mb_substr($p[2] ?? '', 0, 2000) ?: null], 'id = ?', [$id]);
            }
        }
        $verdict = trim(strtolower((string)ContentWriter::tag($res['text'], 'verdict')));
        $proposal = (string)ContentWriter::tag($res['text'], 'proposal');
        $data = [];
        if (isset(R::VERDICTS[$verdict]) && ($overwrite || !$project['website_verdict'])) {
            $data['website_verdict'] = $verdict;
        }
        if ($proposal !== '') {
            $data['website_proposal'] = $overwrite ? trim($proposal) : self::merge((string)$project['website_proposal'], $proposal);
        }
        if ($data) {
            db()->update('projects', $data, 'id = ?', [$project['id']]);
        }
        db()->query(
            'REPLACE INTO project_research (project_id, section, content, is_done, updated_by) VALUES (?, ?, ?, 1, ?)',
            [$project['id'], 'audit_checks', json_encode($result['checks'], JSON_UNESCAPED_UNICODE), $ctx['user_id']]
        );
    }

    // ------------------------------------------------------------------ Gợi ý từ khóa

    public static function keywords(array $project, int $count, array $ctx): void
    {
        $count = max(10, min(100, $count));
        $existing = self::topKeywords((int)$project['id'], 150);
        $comp = db()->fetchAll(
            'SELECT k.keyword, k.volume FROM competitor_keywords k JOIN competitors c ON c.id = k.competitor_id WHERE c.project_id = ? ORDER BY k.traffic DESC, k.volume DESC LIMIT 80',
            [$project['id']]
        );
        $compText = implode("\n", array_map(fn($k) => '- ' . $k['keyword'] . ($k['volume'] ? " ({$k['volume']})" : ''), $comp));
        $products = mb_substr(self::section((int)$project['id'], 'product_research'), 0, 2500);
        $behavior = mb_substr(self::section((int)$project['id'], 'behavior'), 0, 1500);

        $prompt = "Đề xuất $count từ khóa SEO MỚI (không trùng danh sách đã có) cho website, ưu tiên từ khóa dài, câu hỏi khách hay tìm, từ khóa mua hàng theo từng sản phẩm.\n"
            . ($products ? "\nSản phẩm / dịch vụ:\n$products\n" : '')
            . ($behavior ? "\nHành vi tìm kiếm của khách:\n$behavior\n" : '')
            . ($existing ? "\nTừ khóa đã có:\n$existing\n" : '')
            . ($compText ? "\nTừ khóa đối thủ đang lên top:\n$compText\n" : '')
            . "\nKHÔNG ghi lượng tìm kiếm (không có số liệu thật). Intent: informational / commercial / transactional / navigational / local. Ưu tiên: 1 cao, 2 trung bình, 3 thấp."
            . " Nhóm chủ đề: ưu tiên dùng lại nhóm đã có nếu phù hợp.\n"
            . "\nTrả lời đúng định dạng, mỗi dòng một từ khóa:\n<keywords>\ntừ khóa | intent | nhóm chủ đề | ưu tiên\n</keywords>";
        $res = AiText::complete(self::system($project), $prompt, 6000, $ctx, 'keywords');
        $n = 0;
        foreach (preg_split('~\R~', (string)(ContentWriter::tag($res['text'], 'keywords') ?? $res['text'])) as $line) {
            $p = array_map(fn($v) => trim($v, " \t`*-•"), explode('|', $line));
            $kw = mb_strtolower($p[0] ?? '');
            if ($kw === '' || count($p) < 2 || $kw === 'từ khóa') {
                continue;
            }
            $intent = in_array($p[1] ?? '', ['informational', 'commercial', 'transactional', 'navigational', 'local'], true) ? $p[1] : null;
            $n += db()->query(
                'INSERT IGNORE INTO keywords (project_id, keyword, intent, cluster, priority, source) VALUES (?, ?, ?, ?, ?, ?)',
                [$project['id'], mb_substr($kw, 0, 255), $intent, mb_substr($p[2] ?? '', 0, 190) ?: null, max(1, min(3, (int)($p[3] ?? 2) ?: 2)), 'AI gợi ý']
            )->rowCount();
        }
        if ($n === 0) {
            throw new \RuntimeException('AI không trả về từ khóa mới hợp lệ, vui lòng thử lại.');
        }
    }

    // ------------------------------------------------------------------ KPI

    public static function kpi(array $project, int $months, array $ctx): void
    {
        $pid = (int)$project['id'];
        $periods = array_column(db()->fetchAll('SELECT DISTINCT period FROM kpis WHERE project_id = ? ORDER BY period', [$pid]), 'period');
        if (!$periods) {
            for ($i = 0; $i < $months; $i++) {
                $periods[] = date('Y-m', strtotime(date('Y-m-01') . " +$i month"));
            }
            foreach ($periods as $period) {
                foreach (array_keys(R::KPI_METRICS) as $metric) {
                    db()->query('INSERT IGNORE INTO kpis (project_id, period, metric) VALUES (?, ?, ?)', [$pid, $period, $metric]);
                }
            }
        }
        $kw = db()->fetch('SELECT COUNT(*) n, COALESCE(SUM(volume),0) vol FROM keywords WHERE project_id = ?', [$pid]);
        $articles = db()->fetch("SELECT COUNT(*) total, SUM(status IN ('published','wp_draft')) published FROM articles WHERE project_id = ?", [$pid]);
        $planned = db()->fetchAll("SELECT DATE_FORMAT(planned_date, '%Y-%m') p, COUNT(*) c FROM articles WHERE project_id = ? AND planned_date IS NOT NULL GROUP BY p", [$pid]);
        $comp = db()->fetchAll(
            'SELECT c.domain, m.period, m.traffic, m.keywords, m.top10 FROM competitor_metrics m JOIN competitors c ON c.id = m.competitor_id WHERE c.project_id = ? ORDER BY m.period DESC LIMIT 20',
            [$pid]
        );
        $actual = db()->fetchAll('SELECT period, metric, actual FROM kpis WHERE project_id = ? AND actual IS NOT NULL', [$pid]);
        $metricList = implode("\n", array_map(fn($k, $v) => "- $k: $v", array_keys(R::KPI_METRICS), R::KPI_METRICS));

        $prompt = "Đề xuất KPI SEO theo tháng cho các tháng: " . implode(', ', $periods) . ".\n"
            . "\nChỉ tiêu cần đặt (mã: tên):\n$metricList\n"
            . "\nDữ liệu hiện có:\n- Website: " . ($project['has_website'] ? 'đã có' : 'chưa có, đang build') . ($project['website_verdict'] ? " (đánh giá: {$project['website_verdict']})" : '')
            . "\n- Bộ từ khóa: {$kw['n']} từ khóa, tổng volume {$kw['vol']}"
            . "\n- Bài viết: {$articles['total']} trong kế hoạch, {$articles['published']} đã đăng"
            . ($planned ? "\n- Bài dự kiến đăng theo tháng: " . implode(', ', array_map(fn($r) => "{$r['p']}: {$r['c']}", $planned)) : '')
            . ($comp ? "\n- Số liệu đối thủ:\n" . implode("\n", array_map(fn($r) => "  {$r['domain']} {$r['period']}: traffic {$r['traffic']}, từ khóa {$r['keywords']}, top10 {$r['top10']}", $comp)) : '')
            . ($actual ? "\n- Số thực tế đã có:\n" . implode("\n", array_map(fn($r) => "  {$r['period']} {$r['metric']}: {$r['actual']}", $actual)) : '')
            . "\n\nĐặt mục tiêu thực tế, tăng dần theo thời gian (SEO cần 3-6 tháng mới thấy rõ kết quả). Chỉ ghi số, không ghi đơn vị.\n"
            . "\nTrả lời đúng định dạng:\n<kpi>\nYYYY-MM | mã chỉ tiêu | số mục tiêu\n</kpi>\n<note>\nGiải thích ngắn cách đặt KPI và giả định.\n</note>";
        $res = AiText::complete(self::system($project), $prompt, 4000, $ctx, 'kpi');
        $n = 0;
        foreach (preg_split('~\R~', (string)ContentWriter::tag($res['text'], 'kpi')) as $line) {
            $p = array_map('trim', explode('|', $line));
            if (count($p) < 3 || !preg_match('~^\d{4}-\d{2}$~', $p[0]) || !isset(R::KPI_METRICS[$p[1]])) {
                continue;
            }
            $value = (float)str_replace([',', ' '], '', $p[2]);
            $n += db()->query('UPDATE kpis SET target = ? WHERE project_id = ? AND period = ? AND metric = ? AND target IS NULL', [$value, $pid, $p[0], $p[1]])->rowCount();
        }
        if ($note = ContentWriter::tag($res['text'], 'note')) {
            db()->query(
                'REPLACE INTO project_research (project_id, section, content, is_done, updated_by) VALUES (?, ?, ?, 1, ?)',
                [$pid, 'kpi_note', $note, $ctx['user_id']]
            );
        }
        if ($n === 0) {
            throw new \RuntimeException('Không có chỉ tiêu nào được điền (các ô mục tiêu đã có số, hoặc AI trả về sai định dạng).');
        }
    }

    // ------------------------------------------------------------------ Rà soát nội dung web

    public static function contentAudit(array $project, int $limit, array $ctx): void
    {
        $links = db()->fetchAll('SELECT * FROM internal_links WHERE project_id = ? AND audit_action IS NULL ORDER BY id LIMIT ' . max(1, min(25, $limit)), [$project['id']]);
        if (!$links) {
            throw new \RuntimeException('Không còn trang nào chưa rà soát.');
        }
        $pages = '';
        foreach ($links as $l) {
            $pg = SiteReader::fetch($l['url'], 700);
            $pages .= "\n\n[ID {$l['id']}] " . ($pg['status'] === 200
                ? "{$pg['final_url']}\nTitle: {$pg['title']}\nH1: " . implode(' | ', $pg['h1']) . "\nSố chữ: {$pg['word_count']}\nHeading: " . implode(' | ', array_slice($pg['headings'], 0, 12)) . "\nTrích: {$pg['text']}"
                : "{$l['url']} – không tải được (HTTP {$pg['status']})");
        }
        $clusters = array_column(db()->fetchAll("SELECT DISTINCT cluster FROM keywords WHERE project_id = ? AND cluster IS NOT NULL AND cluster <> '' LIMIT 60", [$project['id']]), 'cluster');
        $types = implode(', ', array_keys(LinkController::PAGE_TYPES));
        $actions = implode(', ', array_keys(LinkController::AUDIT_ACTIONS));
        $prompt = "Rà soát nội dung các trang đang có trên website để lên phương án tối ưu.\n"
            . ($clusters ? "\nNhóm chủ đề trong bộ từ khóa: " . implode(', ', $clusters) . "\n" : '')
            . "\nCác trang:$pages\n"
            . "\nVới mỗi trang xác định: loại trang ($types), nhóm chủ đề (ưu tiên dùng nhóm có sẵn), đề xuất ($actions):"
            . " keep = tốt, giữ nguyên; optimize = cần viết lại / bổ sung; merge = trùng chủ đề với trang khác, nên gộp; delete = mỏng / không giá trị, nên xóa hoặc chuyển hướng."
            . " Ghi chú ngắn lý do và việc cần làm.\n"
            . "\nTrả lời đúng định dạng, mỗi dòng một trang:\n<pages>\nID | loại trang | nhóm chủ đề | đề xuất | ghi chú\n</pages>";
        $res = AiText::complete(self::system($project), $prompt, 6000, $ctx, 'content_audit');
        $valid = array_column($links, null, 'id');
        $n = 0;
        foreach (preg_split('~\R~', (string)ContentWriter::tag($res['text'], 'pages')) as $line) {
            $p = array_map('trim', explode('|', $line, 5));
            $id = (int)preg_replace('~\D~', '', $p[0] ?? '');
            if (!isset($valid[$id]) || count($p) < 4) {
                continue;
            }
            db()->update('internal_links', [
                'page_type' => isset(LinkController::PAGE_TYPES[$p[1]]) ? $p[1] : null,
                'cluster' => mb_substr($p[2], 0, 190) ?: null,
                'audit_action' => isset(LinkController::AUDIT_ACTIONS[$p[3]]) ? $p[3] : null,
                'audit_note' => mb_substr($p[4] ?? '', 0, 2000) ?: null,
            ], 'id = ?', [$id]);
            $n++;
        }
        if ($n === 0) {
            throw new \RuntimeException('AI trả về sai định dạng, vui lòng thử lại.');
        }
    }
}
