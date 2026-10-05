<?php
declare(strict_types=1);

namespace App\Services;

/** Đọc sitemap.xml (kể cả sitemap index của Yoast/RankMath) để lấy danh sách URL. */
class SitemapService
{
    /** URL chứa các mẫu này sẽ bị bỏ qua (trang tag, tác giả, phân trang...). */
    private const SKIP_PATTERNS = ['~/tag/~', '~/author/~', '~/page/\d+~', '~/feed/?$~', '~\?~', '~\.(jpg|jpeg|png|gif|webp|pdf|xml)$~i'];

    public static function collect(string $sitemapUrl, int $maxUrls = 5000): array
    {
        $urls = [];
        $queue = [$sitemapUrl];
        $seen = [];
        while ($queue && count($seen) < 60 && count($urls) < $maxUrls) {
            $current = array_shift($queue);
            if (isset($seen[$current])) {
                continue;
            }
            $seen[$current] = true;
            $res = http_request('GET', $current, ['timeout' => 45]);
            if ($res['status'] !== 200) {
                if (count($seen) === 1) {
                    throw new \RuntimeException('Không tải được sitemap (HTTP ' . $res['status'] . ($res['error'] ? ', ' . $res['error'] : '') . ').');
                }
                continue;
            }
            $xml = self::parse($res['body']);
            if ($xml === null) {
                if (count($seen) === 1) {
                    throw new \RuntimeException('Sitemap không đúng định dạng XML.');
                }
                continue;
            }
            $root = strtolower($xml->getName());
            $nodes = $root === 'sitemapindex' ? $xml->sitemap : $xml->url;
            foreach ($nodes as $node) {
                $loc = trim((string)$node->loc);
                if ($loc === '') {
                    continue;
                }
                if ($root === 'sitemapindex') {
                    $queue[] = $loc;
                } elseif (!self::shouldSkip($loc)) {
                    $urls[$loc] = true;
                }
            }
        }
        return array_slice(array_keys($urls), 0, $maxUrls);
    }

    private static function parse(string $body): ?\SimpleXMLElement
    {
        if (str_starts_with($body, "\x1f\x8b")) {
            $body = (string)@gzdecode($body);
        }
        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        return $xml === false ? null : $xml;
    }

    private static function shouldSkip(string $url): bool
    {
        foreach (self::SKIP_PATTERNS as $p) {
            if (preg_match($p, $url)) {
                return true;
            }
        }
        return false;
    }

    /** Tạo tiêu đề tạm từ slug URL. */
    public static function titleFromUrl(string $url): string
    {
        $path = trim((string)parse_url($url, PHP_URL_PATH), '/');
        if ($path === '') {
            return 'Trang chủ';
        }
        $slug = basename($path);
        $slug = preg_replace('~\.(html?|php)$~', '', $slug) ?? $slug;
        return ucfirst(str_replace(['-', '_'], ' ', urldecode($slug)));
    }

    /** Lấy thẻ <title> thật của trang. */
    public static function fetchTitle(string $url): ?string
    {
        $res = http_request('GET', $url, ['timeout' => 20]);
        if ($res['status'] !== 200) {
            return null;
        }
        $html = substr($res['body'], 0, 300000);
        if (preg_match('~<meta[^>]+property=["\']og:title["\'][^>]+content=["\']([^"\']+)~i', $html, $m)
            || preg_match('~<title[^>]*>(.*?)</title>~is', $html, $m)) {
            $title = trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES, 'UTF-8'));
            return mb_substr($title, 0, 500) ?: null;
        }
        return null;
    }
}
