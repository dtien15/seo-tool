<?php
declare(strict_types=1);

namespace App\Services;

/** Kiểm tra kỹ thuật tự động một website (không cần AI). */
class SiteAudit
{
    /** @return array{checks: list<array{0:string,1:?bool,2:string}>, home: array, pagespeed: ?array} */
    public static function run(string $base): array
    {
        $checks = [];
        $add = function (string $label, ?bool $ok, string $detail) use (&$checks) {
            $checks[] = [$label, $ok, $detail];
        };
        $host = (string)parse_url($base, PHP_URL_HOST);
        $port = parse_url($base, PHP_URL_PORT);
        $portSuffix = $port ? ':' . $port : '';
        $bare = preg_replace('~^www\.~', '', $host);

        $home = SiteReader::fetch($base, 3000);
        $add('Trang chủ tải được', $home['status'] === 200, 'HTTP ' . $home['status'] . ($home['error'] ? ' – ' . $home['error'] : '') . sprintf(' · %.1fs · %d KB', $home['time'], $home['bytes'] / 1024));
        $add('Dùng HTTPS', str_starts_with($home['final_url'], 'https://'), 'URL cuối: ' . $home['final_url']);

        $http = http_request('GET', 'http://' . $host . $portSuffix . '/', ['follow' => false, 'timeout' => 15]);
        $loc = $http['headers']['location'] ?? '';
        $add('Chuyển hướng http → https', in_array($http['status'], [301, 308], true) && str_starts_with($loc, 'https://'), 'http:// trả về ' . $http['status'] . ($loc ? ' → ' . $loc : ''));

        $altHost = str_starts_with($host, 'www.') ? $bare : 'www.' . $bare;
        $alt = http_request('GET', (str_starts_with($base, 'https') ? 'https://' : 'http://') . $altHost . $portSuffix . '/', ['follow' => false, 'timeout' => 15]);
        $altLoc = $alt['headers']['location'] ?? '';
        $add('www / không www thống nhất', $alt['status'] === 0 || (in_array($alt['status'], [301, 308], true) && str_contains($altLoc, $host)), $altHost . ' trả về ' . ($alt['status'] ?: 'không phân giải') . ($altLoc ? ' → ' . $altLoc : ''));

        $robots = http_request('GET', $base . '/robots.txt', ['timeout' => 15]);
        $robotsOk = $robots['status'] === 200;
        $blockAll = $robotsOk && self::blocksAll($robots['body']);
        $add('robots.txt', $robotsOk && !$blockAll, $robotsOk ? ($blockAll ? 'ĐANG CHẶN TOÀN BỘ website (Disallow: /)' : 'Có, không chặn toàn site') : 'Không có (HTTP ' . $robots['status'] . ')');

        $sitemapUrl = preg_match('~^Sitemap:\s*(\S+)~mi', $robots['body'], $m) ? $m[1] : null;
        $sitemapOk = false;
        foreach (array_filter([$sitemapUrl, $base . '/sitemap_index.xml', $base . '/sitemap.xml', $base . '/wp-sitemap.xml']) as $u) {
            $r = http_request('GET', $u, ['timeout' => 15]);
            if ($r['status'] === 200 && str_contains($r['body'], '<loc>')) {
                $sitemapOk = true;
                $sitemapUrl = $u;
                break;
            }
        }
        $add('sitemap.xml', $sitemapOk, $sitemapOk ? $sitemapUrl : 'Không tìm thấy sitemap hợp lệ');

        $nf = http_request('GET', $base . '/trang-khong-ton-tai-' . substr(md5((string)time()), 0, 6), ['timeout' => 15]);
        $add('Trang lỗi trả về 404', $nf['status'] === 404, 'Trang không tồn tại trả về HTTP ' . $nf['status']);

        if ($home['status'] === 200) {
            $tl = mb_strlen($home['title']);
            $add('Title trang chủ (30-65 ký tự)', $tl >= 30 && $tl <= 65, "\"{$home['title']}\" ($tl ký tự)");
            $dl = mb_strlen($home['meta_description']);
            $add('Meta description (120-160 ký tự)', $dl >= 120 && $dl <= 160, $dl ? "$dl ký tự" : 'Không có');
            $add('Đúng 1 thẻ H1', count($home['h1']) === 1, count($home['h1']) . ' thẻ H1' . ($home['h1'] ? ': ' . mb_strimwidth(implode(' | ', $home['h1']), 0, 120, '…') : ''));
            $add('Thẻ canonical', $home['canonical'] !== '', $home['canonical'] ?: 'Không có');
            $add('Thẻ viewport (mobile)', $home['viewport'], $home['viewport'] ? 'Có' : 'Không có');
            $add('Ảnh có alt', $home['images'] === 0 || $home['images_no_alt'] / $home['images'] < 0.2, $home['images_no_alt'] . '/' . $home['images'] . ' ảnh thiếu alt');
            $add('Schema (JSON-LD)', $home['schema'], $home['schema'] ? 'Có' : 'Không có');
            $noindex = str_contains(strtolower($home['robots_meta']), 'noindex');
            $add('Trang chủ không bị noindex', !$noindex, $home['robots_meta'] ?: 'Không có thẻ robots');
        }

        $pagespeed = self::pagespeed($home['final_url'] ?: $base);
        if ($pagespeed) {
            $add('PageSpeed mobile ≥ 50', $pagespeed['score'] >= 50, 'Điểm ' . $pagespeed['score'] . ' · LCP ' . $pagespeed['lcp'] . ' · CLS ' . $pagespeed['cls'] . ' · TBT ' . $pagespeed['tbt']);
        } else {
            $add('PageSpeed mobile', null, \App\Settings::has('pagespeed_api_key')
                ? 'Không lấy được điểm PageSpeed lúc này, thử lại sau'
                : 'Chưa có PageSpeed API key (miễn phí) trong Cài đặt hệ thống nên không chấm được điểm tốc độ');
        }
        return ['checks' => $checks, 'home' => $home, 'pagespeed' => $pagespeed];
    }

    /** robots.txt có "Disallow: /" cho User-agent: * không */
    private static function blocksAll(string $robots): bool
    {
        $inStar = false;
        foreach (preg_split('~\R~', $robots) as $line) {
            $line = trim(preg_replace('~#.*$~', '', $line) ?? '');
            if (preg_match('~^user-agent:\s*(.+)$~i', $line, $m)) {
                $inStar = trim($m[1]) === '*';
            } elseif ($inStar && preg_match('~^disallow:\s*/\s*$~i', $line)) {
                return true;
            }
        }
        return false;
    }

    /** Google PageSpeed Insights (miễn phí, không cần key với lưu lượng thấp). */
    public static function pagespeed(string $url): ?array
    {
        $key = (string)\App\Settings::get('pagespeed_api_key', '');
        $res = http_request('GET', 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?strategy=mobile&category=performance&url=' . rawurlencode($url)
            . ($key !== '' ? '&key=' . rawurlencode($key) : ''), ['timeout' => 120]);
        $lh = $res['json']['lighthouseResult'] ?? null;
        if (!$lh) {
            return null;
        }
        $a = $lh['audits'] ?? [];
        return [
            'score' => (int)round(($lh['categories']['performance']['score'] ?? 0) * 100),
            'lcp' => $a['largest-contentful-paint']['displayValue'] ?? '?',
            'cls' => $a['cumulative-layout-shift']['displayValue'] ?? '?',
            'tbt' => $a['total-blocking-time']['displayValue'] ?? '?',
        ];
    }

    public static function toText(array $result): string
    {
        $lines = [];
        foreach ($result['checks'] as [$label, $ok, $detail]) {
            $lines[] = ($ok === null ? '[?]' : ($ok ? '[ĐẠT]' : '[LỖI]')) . " $label: $detail";
        }
        return implode("\n", $lines);
    }
}
