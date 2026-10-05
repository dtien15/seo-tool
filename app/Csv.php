<?php
declare(strict_types=1);

namespace App;

/** Đọc file CSV/TSV xuất từ Ahrefs, Semrush, Google Sheet... (tự nhận UTF-16/UTF-8 và dấu phân cách). */
class Csv
{
    /** Các tên cột thường gặp => tên chuẩn */
    private const ALIASES = [
        'keyword' => ['keyword', 'keywords', 'từ khóa', 'tu khoa', 'query', 'search term'],
        'position' => ['position', 'current position', 'pos', 'vị trí', 'rank', 'ranking'],
        'volume' => ['volume', 'search volume', 'avg. monthly searches', 'lượng tìm kiếm', 'sv'],
        'kd' => ['kd', 'keyword difficulty', 'difficulty', 'độ khó'],
        'traffic' => ['traffic', 'organic traffic', 'current traffic', 'lưu lượng'],
        'url' => ['url', 'current url', 'landing page', 'trang'],
        'intent' => ['intent', 'intents', 'search intent'],
        'cluster' => ['cluster', 'nhóm', 'nhóm chủ đề', 'topic', 'parent topic', 'group'],
        'period' => ['date', 'period', 'tháng', 'month'],
        'keywords' => ['organic keywords', 'keywords count', 'số từ khóa'],
        'top10' => ['top 10', 'top10', 'keywords top 10'],
        'referring_domains' => ['referring domains', 'ref. domains', 'rd', 'domains'],
        'backlinks' => ['backlinks', 'total backlinks'],
    ];

    /** @return list<array<string,string>> */
    public static function read(string $path, int $maxRows = 20000): array
    {
        $raw = (string)file_get_contents($path);
        if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF")) {
            $raw = (string)mb_convert_encoding($raw, 'UTF-8', 'UTF-16');
        } elseif (!mb_check_encoding($raw, 'UTF-8')) {
            $raw = (string)mb_convert_encoding($raw, 'UTF-8', 'Windows-1258');
        }
        $raw = preg_replace('~^\xEF\xBB\xBF~', '', $raw) ?? $raw;
        $lines = preg_split('~\r\n|\r|\n~', trim($raw)) ?: [];
        if (!$lines) {
            return [];
        }
        $first = $lines[0];
        $delimiter = "\t";
        $best = substr_count($first, "\t");
        foreach ([',', ';'] as $d) {
            if (substr_count($first, $d) > $best) {
                $best = substr_count($first, $d);
                $delimiter = $d;
            }
        }
        $header = array_map(fn($h) => self::normalizeHeader((string)$h), str_getcsv($first, $delimiter, '"', '\\'));
        $rows = [];
        foreach (array_slice($lines, 1, $maxRows) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cells = str_getcsv($line, $delimiter, '"', '\\');
            $row = [];
            foreach ($header as $i => $key) {
                if ($key !== '' && !isset($row[$key])) {
                    $row[$key] = trim((string)($cells[$i] ?? ''));
                }
            }
            $rows[] = $row;
        }
        return $rows;
    }

    private static function normalizeHeader(string $h): string
    {
        $h = mb_strtolower(trim($h, " \t\"'"));
        foreach (self::ALIASES as $key => $names) {
            if (in_array($h, $names, true)) {
                return $key;
            }
        }
        return $h;
    }

    public static function int(?string $v): ?int
    {
        if ($v === null || trim($v) === '' || $v === '-') {
            return null;
        }
        $v = str_replace([',', '.', ' '], '', $v);
        return is_numeric($v) ? (int)$v : null;
    }
}
