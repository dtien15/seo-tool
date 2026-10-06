<?php
declare(strict_types=1);

namespace App\Services;

/** Đọc nội dung một trang web (tiêu đề, meta, heading, chữ, link) để AI phân tích. */
class SiteReader
{
    private const UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126 Safari/537.36';

    /** "vnmark.vn" / "https://vnmark.vn/abc" -> "https://vnmark.vn" */
    public static function base(string $domainOrUrl): string
    {
        $v = trim($domainOrUrl);
        if ($v === '') {
            return '';
        }
        if (!preg_match('~^https?://~i', $v)) {
            $v = 'https://' . $v;
        }
        $p = parse_url($v);
        return ($p['scheme'] ?? 'https') . '://' . ($p['host'] ?? '') . (isset($p['port']) ? ':' . $p['port'] : '');
    }

    public static function fetch(string $url, int $maxText = 4000): array
    {
        $res = http_request('GET', $url, ['timeout' => 25, 'user_agent' => self::UA, 'headers' => ['Accept-Language' => 'vi,en;q=0.8']]);
        $out = [
            'url' => $url, 'final_url' => $res['final_url'] ?: $url, 'status' => $res['status'], 'error' => $res['error'],
            'time' => $res['time'], 'bytes' => strlen($res['body']),
            'title' => '', 'meta_description' => '', 'canonical' => '', 'viewport' => false, 'lang' => '', 'robots_meta' => '',
            'h1' => [], 'headings' => [], 'images' => 0, 'images_no_alt' => 0, 'schema' => false,
            'text' => '', 'word_count' => 0, 'links' => [],
        ];
        if ($res['status'] !== 200 || !str_contains(strtolower($res['headers']['content-type'] ?? 'text/html'), 'html')) {
            return $out;
        }
        $html = $res['body'];
        $out['schema'] = str_contains($html, 'application/ld+json');

        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_NOWARNING | LIBXML_NOERROR);
        $xp = new \DOMXPath($dom);
        $first = fn(string $q, string $attr = '') => ($n = $xp->query($q)->item(0)) ? trim($attr ? $n->getAttribute($attr) : $n->textContent) : '';

        $out['title'] = $first('//title');
        $out['meta_description'] = $first('//meta[translate(@name,"DESCRIPTION","description")="description"]', 'content');
        $out['canonical'] = $first('//link[@rel="canonical"]', 'href');
        $out['viewport'] = $xp->query('//meta[@name="viewport"]')->length > 0;
        $out['lang'] = $first('//html', 'lang');
        $out['robots_meta'] = $first('//meta[@name="robots"]', 'content');
        foreach ($xp->query('//h1') as $n) {
            $out['h1'][] = self::clean($n->textContent);
        }
        foreach ($xp->query('//h2|//h3') as $i => $n) {
            if ($i >= 40) {
                break;
            }
            $out['headings'][] = strtoupper($n->nodeName) . ': ' . self::clean($n->textContent);
        }
        foreach ($xp->query('//img') as $img) {
            $out['images']++;
            if (trim($img->getAttribute('alt')) === '') {
                $out['images_no_alt']++;
            }
        }
        $host = parse_url($out['final_url'], PHP_URL_HOST);
        foreach ($xp->query('//a[@href]') as $a) {
            $href = self::absolute(trim($a->getAttribute('href')), $out['final_url']);
            if ($href && parse_url($href, PHP_URL_HOST) === $host && !preg_match('~\.(jpg|jpeg|png|gif|webp|pdf|zip)$|/(cart|gio-hang|checkout|thanh-toan|wp-login|my-account|tai-khoan|wp-admin)~i', $href)) {
                $out['links'][strtok($href, '#')] = self::clean($a->textContent);
            }
            if (count($out['links']) >= 80) {
                break;
            }
        }
        foreach ($xp->query('//script|//style|//noscript|//svg') as $n) {
            $n->parentNode?->removeChild($n);
        }
        $body = $xp->query('//body')->item(0);
        $text = self::clean($body ? $body->textContent : '');
        $out['word_count'] = $text === '' ? 0 : count(preg_split('~\s+~u', $text));
        $out['text'] = mb_substr($text, 0, $maxText);
        return $out;
    }

    /** Trang chủ + vài trang con quan trọng (lấy từ menu / link trên trang chủ). */
    public static function sample(string $base, int $extraPages = 4, int $maxText = 3000): array
    {
        $home = self::fetch($base, $maxText);
        $pages = [$home];
        $picked = 0;
        foreach (array_keys($home['links']) as $link) {
            if ($picked >= $extraPages) {
                break;
            }
            $path = trim((string)parse_url($link, PHP_URL_PATH), '/');
            if ($path === '' || str_contains($path, 'tag/') || str_contains($path, 'author/') || preg_match('~page/\d~', $path)) {
                continue;
            }
            $pages[] = self::fetch($link, $maxText);
            $picked++;
        }
        return $pages;
    }

    /** Tóm tắt trang thành văn bản ngắn để đưa vào prompt. */
    public static function summarize(array $page): string
    {
        if ($page['status'] !== 200) {
            return "URL: {$page['url']}\n(Không tải được – HTTP {$page['status']} {$page['error']})";
        }
        return "URL: {$page['final_url']}\nTitle: {$page['title']}\nMeta description: {$page['meta_description']}\n"
            . 'H1: ' . implode(' | ', $page['h1']) . "\n"
            . 'Heading: ' . implode(' | ', array_slice($page['headings'], 0, 25)) . "\n"
            . "Số chữ: {$page['word_count']}\nNội dung (trích): {$page['text']}";
    }

    private static function clean(string $s): string
    {
        return trim(preg_replace('~\s+~u', ' ', $s) ?? $s);
    }

    private static function absolute(string $href, string $base): ?string
    {
        if ($href === '' || preg_match('~^(mailto:|tel:|javascript:|#)~i', $href)) {
            return null;
        }
        if (preg_match('~^https?://~i', $href)) {
            return $href;
        }
        $b = parse_url($base);
        $root = ($b['scheme'] ?? 'https') . '://' . ($b['host'] ?? '') . (isset($b['port']) ? ':' . $b['port'] : '');
        if (str_starts_with($href, '//')) {
            return ($b['scheme'] ?? 'https') . ':' . $href;
        }
        if (str_starts_with($href, '/')) {
            return $root . $href;
        }
        $dir = rtrim(dirname($b['path'] ?? '/'), '/');
        return $root . $dir . '/' . $href;
    }
}
