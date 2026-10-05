<?php
declare(strict_types=1);

namespace App\Services;

/** Dựng prompt và phân tích kết quả viết bài SEO từ Claude. */
class ContentWriter
{
    public static function systemPrompt(array $project): string
    {
        $brief = [
            'Tên dự án / thương hiệu' => $project['name'],
            'Website' => $project['domain'],
            'Lĩnh vực' => $project['niche'],
            'Giọng văn thương hiệu' => $project['brand_voice'],
            'Đối tượng độc giả' => $project['target_audience'],
            'Từ khóa chính của website' => $project['main_keywords'],
            'Quy tắc nội dung bắt buộc' => $project['content_rules'],
        ];
        $lines = [];
        foreach ($brief as $label => $value) {
            if (trim((string)$value) !== '') {
                $lines[] = "- {$label}: " . trim((string)$value);
            }
        }
        $briefText = $lines ? implode("\n", $lines) : '- (chưa có thông tin thêm)';

        return <<<TXT
Bạn là biên tập viên SEO tiếng Việt nhiều kinh nghiệm, viết cho website dưới đây.

Thông tin dự án:
{$briefText}

Nguyên tắc chung:
- Viết tiếng Việt tự nhiên, chính xác, hữu ích thật sự cho người đọc; tránh văn mẫu sáo rỗng, tránh nhồi từ khóa.
- Tuân thủ E-E-A-T: thông tin cụ thể, ví dụ thực tế, không bịa số liệu, nghiên cứu hay trích dẫn. Nếu không chắc chắn thì diễn đạt thận trọng.
- Đáp ứng đúng search intent của từ khóa; trả lời ý chính sớm trong bài.
- Không nhắc đến việc bạn là AI.
TXT;
    }

    public static function outlinePrompt(array $project, array $article): string
    {
        $words = (int)($article['word_count'] ?: $project['word_count']);
        $secondary = trim((string)$article['secondary_keywords']) ?: '(không có)';
        $notes = trim((string)$article['notes']) ?: '(không có)';
        return <<<TXT
Hãy lập dàn ý (outline) chuẩn SEO cho bài viết.

Từ khóa chính: {$article['keyword']}
Từ khóa phụ: {$secondary}
Ghi chú của SEOer: {$notes}
Độ dài mục tiêu: khoảng {$words} từ

Yêu cầu:
- Phân tích ngắn search intent (1-2 câu).
- Đề xuất 1 tiêu đề (H1) hấp dẫn, chứa từ khóa chính, dưới 65 ký tự.
- Dàn ý dùng H2/H3, mỗi mục kèm 1 dòng gợi ý nội dung cần viết; có phần FAQ nếu phù hợp.

Trả lời đúng định dạng sau, không thêm gì khác:
<title>tiêu đề</title>
<outline>
Search intent: ...
## H2 ...
- gợi ý nội dung
### H3 ...
- gợi ý nội dung
</outline>
TXT;
    }

    public static function writePrompt(array $project, array $article, array $links, int $imageCount): string
    {
        $words = (int)($article['word_count'] ?: $project['word_count']);
        $secondary = trim((string)$article['secondary_keywords']) ?: '(không có)';
        $notes = trim((string)$article['notes']) ?: '(không có)';
        $outline = trim((string)$article['outline']);
        $outlineBlock = $outline !== ''
            ? "Dàn ý đã được duyệt (bám sát dàn ý này):\n{$outline}"
            : 'Chưa có dàn ý: hãy tự xây dựng cấu trúc H2/H3 hợp lý.';
        $titleHint = trim((string)$article['title']) !== '' ? "Tiêu đề mong muốn: {$article['title']}" : '';

        if ($links) {
            $linkLines = implode("\n", array_map(fn($l) => '- ' . $l['url'] . ' | ' . ($l['title'] ?: ''), $links));
            $linkBlock = <<<TXT
Danh sách link nội bộ có thể chèn (URL | tiêu đề):
{$linkLines}

Chèn 3-5 link nội bộ phù hợp nhất ở các đoạn liên quan, dạng <a href="URL">anchor text tự nhiên</a>.
Chỉ dùng URL có trong danh sách trên, mỗi URL tối đa 1 lần, không chèn link trong thẻ heading.
TXT;
        } else {
            $linkBlock = 'Không có danh sách link nội bộ: không chèn link nội bộ.';
        }

        $imgLines = ["IMAGE_0 | alt text ảnh đại diện | prompt tiếng Anh mô tả ảnh đại diện"];
        for ($i = 1; $i <= $imageCount; $i++) {
            $imgLines[] = "IMAGE_{$i} | alt text | prompt tiếng Anh";
        }
        $imgFormat = implode("\n", $imgLines);
        $placeholderRule = $imageCount > 0
            ? "Đặt {$imageCount} dòng giữ chỗ ảnh [IMAGE_1]" . ($imageCount > 1 ? " ... [IMAGE_{$imageCount}]" : '') . " (mỗi dòng giữ chỗ là một đoạn riêng <p>[IMAGE_n]</p>) ở vị trí minh họa phù hợp, rải đều trong bài, không đặt ngay đầu bài."
            : 'Không đặt ảnh minh họa trong thân bài.';

        return <<<TXT
Viết một bài SEO hoàn chỉnh.

Từ khóa chính: {$article['keyword']}
Từ khóa phụ: {$secondary}
Ghi chú của SEOer: {$notes}
Độ dài: khoảng {$words} từ
{$titleHint}

{$outlineBlock}

{$linkBlock}

Yêu cầu định dạng nội dung:
- Thân bài là HTML sạch: chỉ dùng <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, <em>, <a>, <table>, <thead>, <tbody>, <tr>, <th>, <td>, <blockquote>. Không dùng <h1>, không dùng CSS/class/style, không có markdown.
- Từ khóa chính xuất hiện trong đoạn mở đầu và ít nhất một thẻ H2, mật độ tự nhiên.
- {$placeholderRule}
- Prompt ảnh viết bằng tiếng Anh, mô tả ảnh chụp/minh họa chân thực, phù hợp nội dung, KHÔNG chứa chữ/text/logo trong ảnh.

Trả lời đúng định dạng sau, không thêm lời dẫn nào khác:
<title>tiêu đề H1, chứa từ khóa chính, dưới 65 ký tự</title>
<slug>slug-khong-dau</slug>
<meta_title>meta title dưới 60 ký tự</meta_title>
<meta_description>meta description 140-155 ký tự, có từ khóa chính</meta_description>
<excerpt>tóm tắt 1-2 câu</excerpt>
<tags>tag 1, tag 2, tag 3</tags>
<content>
...HTML thân bài...
</content>
<images>
{$imgFormat}
</images>
TXT;
    }

    public static function tag(string $text, string $tag): ?string
    {
        if (preg_match('~<' . $tag . '>(.*?)</' . $tag . '>~s', $text, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    /** Phân tích kết quả viết bài. */
    public static function parseArticle(string $text): array
    {
        $content = self::tag($text, 'content');
        if ($content === null || mb_strlen(strip_tags($content)) < 200) {
            throw new \RuntimeException('Kết quả AI không đúng định dạng (thiếu nội dung). Hãy thử lại.');
        }
        $content = self::sanitizeHtml($content);

        $images = [];
        foreach (preg_split('~\R~', (string)self::tag($text, 'images')) as $line) {
            $parts = array_map('trim', explode('|', $line, 3));
            if (count($parts) === 3 && preg_match('~IMAGE_(\d+)~', $parts[0], $m)) {
                $images[(int)$m[1]] = ['alt' => mb_substr($parts[1], 0, 250), 'prompt' => $parts[2]];
            }
        }
        ksort($images);

        $slug = slugify((string)self::tag($text, 'slug'));
        $title = strip_tags((string)self::tag($text, 'title'));
        return [
            'title' => mb_substr($title, 0, 500),
            'slug' => $slug !== '' ? mb_substr($slug, 0, 200) : slugify($title),
            'meta_title' => mb_substr(strip_tags((string)self::tag($text, 'meta_title')), 0, 255),
            'meta_description' => mb_substr(strip_tags((string)self::tag($text, 'meta_description')), 0, 500),
            'excerpt' => strip_tags((string)self::tag($text, 'excerpt')),
            'tags' => array_values(array_filter(array_map('trim', explode(',', (string)self::tag($text, 'tags'))))),
            'content' => $content,
            'images' => $images,
        ];
    }

    public static function sanitizeHtml(string $html): string
    {
        $html = preg_replace('~^```(?:html)?\s*|\s*```$~', '', trim($html)) ?? $html;
        $html = preg_replace('~<(script|style|iframe|object|embed)\b[^>]*>.*?</\1>~is', '', $html) ?? $html;
        $html = preg_replace('~\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)~i', '', $html) ?? $html;
        $html = preg_replace('~<h1\b[^>]*>(.*?)</h1>~is', '<h2>$1</h2>', $html) ?? $html;
        return trim($html);
    }

    /** HTML cho một ảnh trong bài. */
    public static function figure(string $src, string $alt): string
    {
        return '<figure class="wp-block-image size-large"><img src="' . e($src) . '" alt="' . e($alt) . '" loading="lazy" /></figure>';
    }
}
