<?php
declare(strict_types=1);

namespace App\Services;

/** Chọn các link nội bộ liên quan nhất tới từ khóa bài viết. */
class InternalLinker
{
    private const STOPWORDS = ['va', 'la', 'cua', 'cho', 'voi', 'cac', 'nhung', 'mot', 'co', 'khong', 'the', 'nao', 'gi', 'o', 'tai', 'tu', 'den', 'trong', 'nhu', 've', 'duoc', 'nen', 'hay', 'khi', 'bao', 'nhieu', 'top', 'tot', 'nhat'];

    private static function tokens(string $text): array
    {
        $words = preg_split('~[^a-z0-9]+~', vn_normalize($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_unique(array_filter($words, fn($w) => mb_strlen($w) > 1 && !in_array($w, self::STOPWORDS, true))));
    }

    /** Tạo các cụm 2 từ liên tiếp để ưu tiên link khớp cụm. */
    private static function bigrams(array $tokens): array
    {
        $out = [];
        for ($i = 0; $i < count($tokens) - 1; $i++) {
            $out[] = $tokens[$i] . ' ' . $tokens[$i + 1];
        }
        return $out;
    }

    public static function candidates(int $projectId, string $keyword, string $secondary = '', int $limit = 15, ?string $excludeUrl = null): array
    {
        $kwTokens = self::tokens($keyword . ' ' . $secondary);
        if (!$kwTokens) {
            return [];
        }
        $kwBigrams = self::bigrams(self::tokens($keyword));
        $scored = [];
        $rows = db()->fetchAll('SELECT url, title, keywords FROM internal_links WHERE project_id = ? LIMIT 20000', [$projectId]);
        foreach ($rows as $row) {
            if ($excludeUrl && rtrim($row['url'], '/') === rtrim($excludeUrl, '/')) {
                continue;
            }
            $haystack = ' ' . implode(' ', self::tokens(($row['title'] ?? '') . ' ' . ($row['keywords'] ?? '') . ' ' . str_replace(['-', '/'], ' ', (string)parse_url($row['url'], PHP_URL_PATH)))) . ' ';
            $score = 0;
            foreach ($kwTokens as $t) {
                if (str_contains($haystack, ' ' . $t . ' ')) {
                    $score += 1;
                }
            }
            foreach ($kwBigrams as $b) {
                if (str_contains($haystack, ' ' . $b . ' ')) {
                    $score += 2;
                }
            }
            if ($score >= 2) {
                $scored[] = $row + ['score' => $score];
            }
        }
        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return array_slice($scored, 0, $limit);
    }
}
